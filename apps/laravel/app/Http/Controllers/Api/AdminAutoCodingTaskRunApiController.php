<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AutoCodingRunArtifactResource;
use App\Http\Resources\AutoCodingTaskRunResource;
use App\Services\AutoCoding\AutoCodingTaskRunQueryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AdminAutoCodingTaskRunApiController extends Controller
{
    public function __construct(
        private readonly AutoCodingTaskRunQueryService $taskRunQueryService,
    ) {}

    /**
     * Return one detailed local auto-coding task run.
     *
     * @param  int  $id
     * @return AutoCodingTaskRunResource
     */
    public function show(int $id): AutoCodingTaskRunResource
    {
        $run = $this->taskRunQueryService->findDetailedById($id);

        abort_if($run === null, Response::HTTP_NOT_FOUND, 'Local auto-coding task run not found.');

        return new AutoCodingTaskRunResource($run);
    }

    /**
     * Return the structured artifacts emitted by one local auto-coding task run.
     *
     * @param  int  $id
     * @return AnonymousResourceCollection
     */
    public function artifacts(int $id): AnonymousResourceCollection
    {
        $run = $this->taskRunQueryService->findDetailedById($id);

        abort_if($run === null, Response::HTTP_NOT_FOUND, 'Local auto-coding task run not found.');

        return AutoCodingRunArtifactResource::collection($run->artifacts);
    }
}
