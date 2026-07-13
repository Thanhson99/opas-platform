<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrawlDouyinRequest;
use App\Http\Requests\MarkDouyinVideoPostedRequest;
use App\Http\Requests\StoreDouyinKeywordRequest;
use App\Http\Requests\UpdateDouyinVideoSelectionRequest;
use App\Http\Resources\DouyinCrawlJobResource;
use App\Http\Resources\DouyinKeywordResource;
use App\Http\Resources\DouyinVideoResource;
use App\Models\DouyinCrawlJob;
use App\Models\DouyinVideo;
use App\Services\Douyin\DouyinWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DouyinApiController extends Controller
{
    public function __construct(
        private readonly DouyinWorkflowService $workflowService,
    ) {}

    /**
     * List active Douyin keywords.
     *
     * @return JsonResponse
     */
    public function keywords(): JsonResponse
    {
        return DouyinKeywordResource::collection($this->workflowService->keywords())->response();
    }

    /**
     * Store a Douyin keyword.
     *
     * @param  StoreDouyinKeywordRequest  $request
     * @return JsonResponse
     */
    public function storeKeyword(StoreDouyinKeywordRequest $request): JsonResponse
    {
        /** @var array{name: string, category?: string|null} $validated */
        $validated = $request->validated();
        $keyword = $this->workflowService->createKeyword($validated);

        return (new DouyinKeywordResource($keyword))->response()->setStatusCode(201);
    }

    /**
     * Crawl preview videos for one keyword.
     *
     * @param  CrawlDouyinRequest  $request
     * @return JsonResponse
     */
    public function crawl(CrawlDouyinRequest $request): JsonResponse
    {
        /** @var array{keyword: string, limit?: int|null} $validated */
        $validated = $request->validated();

        try {
            $job = $this->workflowService->crawlPreview(
                $validated['keyword'],
                $validated['limit'] ?? 20
            );
        } catch (RuntimeException $exception) {
            return $this->workerUnavailableResponse($exception);
        }

        return (new DouyinCrawlJobResource($job))->response()->setStatusCode(201);
    }

    /**
     * List recent Douyin crawl jobs.
     *
     * @return JsonResponse
     */
    public function jobs(): JsonResponse
    {
        return DouyinCrawlJobResource::collection($this->workflowService->jobs())->response();
    }

    /**
     * Show a crawl job with its videos.
     *
     * @param  DouyinCrawlJob  $job
     * @return JsonResponse
     */
    public function showJob(DouyinCrawlJob $job): JsonResponse
    {
        return (new DouyinCrawlJobResource($job->load('videos')))->response();
    }

    /**
     * Update video selection.
     *
     * @param  UpdateDouyinVideoSelectionRequest  $request
     * @param  DouyinVideo  $video
     * @return JsonResponse
     */
    public function updateSelection(UpdateDouyinVideoSelectionRequest $request, DouyinVideo $video): JsonResponse
    {
        $updatedVideo = $this->workflowService->updateSelection(
            $video,
            (bool) $request->validated('selected')
        );

        return (new DouyinVideoResource($updatedVideo))->response();
    }

    /**
     * Download selected preview videos.
     *
     * @param  DouyinCrawlJob  $job
     * @return JsonResponse
     */
    public function processSelected(DouyinCrawlJob $job): JsonResponse
    {
        try {
            return DouyinVideoResource::collection(
                $this->workflowService->processSelected($job)
            )->response();
        } catch (RuntimeException $exception) {
            return $this->workerUnavailableResponse($exception);
        }
    }

    /**
     * List stored Douyin videos.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function videos(Request $request): JsonResponse
    {
        /** @var array{status?: string|null, keyword?: string|null, page?: int|null} $filters */
        $filters = $request->validate([
            'status' => 'nullable|string|max:80',
            'keyword' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
        ]);

        return DouyinVideoResource::collection($this->workflowService->videos($filters))->response();
    }

    /**
     * Mark a video as posted.
     *
     * @param  MarkDouyinVideoPostedRequest  $request
     * @param  DouyinVideo  $video
     * @return JsonResponse
     */
    public function markPosted(MarkDouyinVideoPostedRequest $request, DouyinVideo $video): JsonResponse
    {
        $updatedVideo = $this->workflowService->markPosted(
            $video,
            (bool) ($request->validated('delete_after_posted') ?? false)
        );

        return (new DouyinVideoResource($updatedVideo))->response();
    }

    /**
     * Delete a video and local files.
     *
     * @param  DouyinVideo  $video
     * @return JsonResponse
     */
    public function destroyVideo(DouyinVideo $video): JsonResponse
    {
        $this->workflowService->deleteVideo($video);

        return response()->json(null, 204);
    }

    /**
     * Return a stable response when the worker is not enabled.
     *
     * @param  RuntimeException  $exception
     * @return JsonResponse
     */
    private function workerUnavailableResponse(RuntimeException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
        ], 503);
    }
}
