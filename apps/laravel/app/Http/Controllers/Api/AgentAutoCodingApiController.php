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

        /** @var array{repository_path?:string,metadata?:array<string, mixed>} $validated */
        $validated = $request->validated();

        $updatedMachine = $this->localMachineService->recordHeartbeat([
            'machine_key' => $machine->machine_key,
            'hostname' => $machine->hostname,
            'operating_system' => $machine->operating_system,
            'repository_path' => isset($validated['repository_path']) ? trim($validated['repository_path']) : $machine->repository_path,
            'metadata' => is_array($validated['metadata'] ?? null) ? $validated['metadata'] : $machine->metadata,
        ]);

        return response()->json([
            'data' => [
                'id' => $updatedMachine->id,
                'machine_key' => $updatedMachine->machine_key,
                'repository_path' => $updatedMachine->repository_path,
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
        /** @var array{execute?:bool} $validated */
        $validated = $request->validated();

        $task = $this->taskDispatchService->claimAndOptionallyExecute(
            $machine->repository_path,
            (bool) ($validated['execute'] ?? false),
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
            $task->repository_path !== $machine->repository_path,
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
}
