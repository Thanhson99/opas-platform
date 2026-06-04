<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentClaimAutoCodingTaskRequest;
use App\Http\Requests\AgentHeartbeatAutoCodingMachineRequest;
use App\Http\Resources\AutoCodingTaskResource;
use App\Http\Resources\AutoCodingTaskStatusResource;
use App\Models\AutoCodingMachine;
use App\Services\AutoCoding\AutoCodingAgentAuthService;
use App\Services\AutoCoding\AutoCodingMachineRoutingService;
use App\Services\AutoCoding\AutoCodingTaskDispatchService;
use App\Services\AutoCoding\AutoCodingTaskQueryService;
use App\Services\AutoCoding\LocalMachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentAutoCodingApiController extends Controller
{
    public function __construct(
        private readonly AutoCodingAgentAuthService $agentAuthService,
        private readonly LocalMachineService $localMachineService,
        private readonly AutoCodingTaskDispatchService $taskDispatchService,
        private readonly AutoCodingTaskQueryService $taskQueryService,
        private readonly AutoCodingMachineRoutingService $machineRoutingService,
    ) {}

    /**
     * Persist one heartbeat reported by an authenticated machine agent.
     *
     * @param  AgentHeartbeatAutoCodingMachineRequest  $request
     * @return JsonResponse
     */
    public function heartbeat(AgentHeartbeatAutoCodingMachineRequest $request): JsonResponse
    {
        $machine = $this->resolveAuthenticatedMachine($request->bearerToken());

        /** @var array{
         *   availability_status?:string,
         *   repository_path?:string,
         *   capabilities?:array<string, mixed>,
         *   workspace_bindings?:array<int, array<string, mixed>>,
         *   max_parallel_tasks?:int,
         *   metadata?:array<string, mixed>
         * } $validated
         */
        $validated = $request->validated();

        $updatedMachine = $this->localMachineService->recordHeartbeat([
            'machine_key' => $machine->machine_key,
            'hostname' => $machine->hostname,
            'operating_system' => $machine->operating_system,
            'availability_status' => $validated['availability_status'] ?? $machine->availability_status,
            'repository_path' => isset($validated['repository_path']) ? trim($validated['repository_path']) : $machine->repository_path,
            'capabilities' => is_array($validated['capabilities'] ?? null)
                ? $validated['capabilities']
                : $machine->capabilities,
            'workspace_bindings' => is_array($validated['workspace_bindings'] ?? null)
                ? $validated['workspace_bindings']
                : $machine->workspace_bindings,
            'max_parallel_tasks' => is_numeric($validated['max_parallel_tasks'] ?? null)
                ? (int) $validated['max_parallel_tasks']
                : $machine->max_parallel_tasks,
            'metadata' => is_array($validated['metadata'] ?? null) ? $validated['metadata'] : $machine->metadata,
        ]);

        return response()->json([
            'data' => [
                'id' => $updatedMachine->id,
                'machine_key' => $updatedMachine->machine_key,
                'repository_path' => $updatedMachine->repository_path,
                'availability_status' => $updatedMachine->availability_status,
                'workspace_bindings' => $updatedMachine->workspace_bindings,
                'last_seen_at' => $updatedMachine->last_seen_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Claim the next pending task for the authenticated machine agent and optionally execute it.
     *
     * @param  AgentClaimAutoCodingTaskRequest  $request
     * @return JsonResponse
     */
    public function claim(AgentClaimAutoCodingTaskRequest $request): JsonResponse
    {
        $machine = $this->resolveAuthenticatedMachine($request->bearerToken());
        /** @var array{execute?:bool,repository_path?:string} $validated */
        $validated = $request->validated();
        $repositoryPath = $this->resolveClaimRepositoryPath($machine, $validated['repository_path'] ?? null);
        $machine = $this->refreshMachineHeartbeatForClaim($machine);

        $task = $this->taskDispatchService->claimAndOptionallyExecute(
            $repositoryPath,
            (bool) ($validated['execute'] ?? false),
            $machine,
        );

        if ($task === null) {
            return response()->json([
                'data' => null,
                'message' => 'No pending local auto-coding task available.',
            ]);
        }

        return (new AutoCodingTaskResource($task))->response();
    }

    /**
     * Return one compact task-status payload to an authenticated machine agent.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return AutoCodingTaskStatusResource
     */
    public function status(Request $request, int $id): AutoCodingTaskStatusResource
    {
        $machine = $this->resolveAuthenticatedMachine($request->bearerToken());
        $task = $this->taskQueryService->findDetailedById($id);

        abort_if($task === null, Response::HTTP_NOT_FOUND, 'Local auto-coding task not found.');
        abort_if(
            ! $this->machineRoutingService->machineMatchesRepository($machine, $task->repository_path),
            Response::HTTP_NOT_FOUND,
            'Local auto-coding task not found.'
        );
        abort_if(
            $task->assigned_machine_id !== null && (int) $task->assigned_machine_id !== (int) $machine->id,
            Response::HTTP_NOT_FOUND,
            'Local auto-coding task not found.'
        );

        return new AutoCodingTaskStatusResource($task);
    }

    /**
     * Resolve the authenticated local machine from one bearer token or abort.
     *
     * @param  string|null  $bearerToken
     * @return AutoCodingMachine
     */
    protected function resolveAuthenticatedMachine(?string $bearerToken): AutoCodingMachine
    {
        $machine = $this->agentAuthService->authenticate($bearerToken);

        abort_if($machine === null, Response::HTTP_UNAUTHORIZED, 'Invalid auto-coding agent token.');

        return $machine;
    }

    /**
     * Resolve and validate the repository path requested by one claim call.
     *
     * @param  AutoCodingMachine  $machine
     * @param  string|null  $repositoryPath
     * @return string|null
     */
    protected function resolveClaimRepositoryPath(AutoCodingMachine $machine, ?string $repositoryPath): ?string
    {
        if (! is_string($repositoryPath) || trim($repositoryPath) === '') {
            return null;
        }

        $requestedRepositoryPath = trim($repositoryPath);

        abort_if(
            ! $this->machineRoutingService->machineMatchesRepository($machine, $requestedRepositoryPath),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Repository path is not bound to this auto-coding machine.'
        );

        return $requestedRepositoryPath;
    }

    /**
     * Refresh heartbeat freshness before claim without changing machine routing context.
     *
     * @param  AutoCodingMachine  $machine
     * @return AutoCodingMachine
     */
    protected function refreshMachineHeartbeatForClaim(AutoCodingMachine $machine): AutoCodingMachine
    {
        return $this->localMachineService->recordHeartbeat([
            'machine_key' => $machine->machine_key,
            'hostname' => $machine->hostname,
            'operating_system' => $machine->operating_system,
            'availability_status' => $machine->availability_status,
            'repository_path' => $machine->repository_path,
            'capabilities' => $machine->capabilities,
            'workspace_bindings' => $machine->workspace_bindings,
            'max_parallel_tasks' => $machine->max_parallel_tasks,
            'metadata' => $machine->metadata,
        ]);
    }
}
