<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrendingVideoIndexRequest;
use App\Http\Resources\VideoGroupResource;
use App\Services\Python\PythonService;
use Illuminate\Http\JsonResponse;

class TrendingVideoApiController extends Controller
{
    public function __construct(
        private readonly PythonService $pythonService,
    ) {}

    public function index(TrendingVideoIndexRequest $request): JsonResponse
    {
        $result = $this->pythonService->trendingKeywords();
        $videos = [];
        $validated = $request->validated();
        $period = is_string($validated['period'] ?? null) ? $validated['period'] : 'day';

        foreach ($result as $keyword => $links) {
            if (! is_array($links)) {
                continue;
            }

            $videos[] = [
                'keyword' => (string) $keyword,
                'links' => array_values(array_filter(
                    $links,
                    static fn (mixed $link): bool => is_string($link) && $link !== '',
                )),
            ];
        }

        return VideoGroupResource::collection(collect($videos))
            ->additional([
                'meta' => [
                    'period' => $period,
                ],
            ])
            ->response();
    }
}
