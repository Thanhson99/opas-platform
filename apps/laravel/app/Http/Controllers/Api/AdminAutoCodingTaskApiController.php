<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimAutoCodingTaskRequest;
use App\Http\Requests\ListAutoCodingTasksRequest;
use App\Http\Requests\StoreAutoCodingTaskRequest;
use App\Http\Resources\AutoCodingTaskResource;
use App\Http\Resources\AutoCodingTaskStatusResource;
use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingTaskDispatchService;
use App\Services\AutoCoding\AutoCodingTaskQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AdminAutoCodingTaskApiController extends Controller
{
    public function __construct(
        private readonly AutoCodingTaskQueryService $taskQueryService,
        private readonly AutoCodingTaskDispatchService $taskDispatchService,
    ) {}

    /**
     * Return the local auto-coding tasks shown in the admin automation view.
     *
     * @param  ListAutoCodingTasksRequest  $request
     * @return AnonymousResourceCollection
     */
    public function index(ListAutoCodingTasksRequest $request): AnonymousResourceCollection
    {
        /** @var array{status?:string,issue_key?:string,per_page?:int} $validated */
        $validated = $request->validated();

        return AutoCodingTaskResource::collection(
            $this->taskQueryService->paginateForAdmin(
                isset($validated['status']) ? trim($validated['status']) : null,
                isset($validated['issue_key']) ? trim($validated['issue_key']) : null,
                (int) ($validated['per_page'] ?? 10),
            )
        );
    }

    /**
     * Return one local auto-coding task with its latest run details.
     *
     * @param  int  $id
     * @return AutoCodingTaskResource
     */
    public function show(int $id): AutoCodingTaskResource
    {
        $task = $this->taskQueryService->findDetailedById($id);

        abort_if($task === null, Response::HTTP_NOT_FOUND, 'Local auto-coding task not found.');

        return new AutoCodingTaskResource($task);
    }

    /**
     * Return one compact local auto-coding task status payload for polling workflows.
     *
     * @param  int  $id
     * @return AutoCodingTaskStatusResource
     */
    public function status(int $id): AutoCodingTaskStatusResource
    {
        $task = $this->taskQueryService->findDetailedById($id);

        abort_if($task === null, Response::HTTP_NOT_FOUND, 'Local auto-coding task not found.');

        return new AutoCodingTaskStatusResource($task);
    }

    /**
     * Claim the oldest pending local auto-coding task for one repository path.
     *
     * @param  ClaimAutoCodingTaskRequest  $request
     * @return JsonResponse
     */
    public function claim(ClaimAutoCodingTaskRequest $request): JsonResponse
    {
        /** @var array{repository_path?:string,execute?:bool} $validated */
        $validated = $request->validated();

        $task = $this->taskDispatchService->claimAndOptionallyExecute(
            isset($validated['repository_path']) ? trim($validated['repository_path']) : null,
            (bool) ($validated['execute'] ?? false),
        );

        if (! $task instanceof AutoCodingTask) {
            return response()->json([
                'data' => null,
                'message' => 'No pending local auto-coding task available.',
            ]);
        }

        return (new AutoCodingTaskResource($task))->response();
    }

    /**
     * Create one pending local auto-coding task from the admin automation API.
     *
     * @param  StoreAutoCodingTaskRequest  $request
     * @return JsonResponse
     */
    public function store(StoreAutoCodingTaskRequest $request): JsonResponse
    {
        /** @var array{
         *   summary:string,
         *   issue_key?:string,
         *   repository_path?:string,
         *   validate?:bool,
         *   provider?:string,
         *   provider_options?:array<string, mixed>
         * } $validated
         */
        $validated = $request->validated();

        $task = $this->taskDispatchService->createPendingTaskFromPayload($validated);

        return (new AutoCodingTaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
