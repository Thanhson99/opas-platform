<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedKeywordRequest;
use App\Http\Resources\FeedKeywordResource;
use App\Services\Coin\FeedKeywordService;
use Illuminate\Http\JsonResponse;

class FeedKeywordApiController extends Controller
{
    public function __construct(
        private readonly FeedKeywordService $keywordService,
    ) {}

    /**
     * Return the full keyword list with nested tags.
     */
    public function index(): JsonResponse
    {
        return FeedKeywordResource::collection(
            $this->keywordService->getAllWithTags()
        )->response();
    }

    /**
     * Store a new keyword record through the existing service layer.
     */
    public function store(StoreFeedKeywordRequest $request): JsonResponse
    {
        $id = $this->keywordService->create($request->validated());

        return response()->json([
            'data' => [
                'id' => $id,
            ],
            'message' => 'Keyword created successfully.',
        ], 201);
    }

    /**
     * Remove a keyword and its related tag mappings.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->keywordService->delete($id);

        return response()->json([
            'message' => 'Keyword deleted successfully.',
        ]);
    }
}
