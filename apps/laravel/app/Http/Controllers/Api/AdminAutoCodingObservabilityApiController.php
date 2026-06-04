<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShowAutoCodingObservabilityReportRequest;
use App\Services\AutoCoding\AutoCodingObservabilityService;
use Illuminate\Http\JsonResponse;

class AdminAutoCodingObservabilityApiController extends Controller
{
    public function __construct(
        private readonly AutoCodingObservabilityService $observabilityService,
    ) {}

    /**
     * Return centralized auto-coding operational visibility.
     *
     * @param  ShowAutoCodingObservabilityReportRequest  $request
     * @return JsonResponse
     */
    public function show(ShowAutoCodingObservabilityReportRequest $request): JsonResponse
    {
        /** @var array{days?:int,repository_path?:string,machine_key?:string} $validated */
        $validated = $request->validated();

        return response()->json([
            'data' => $this->observabilityService->buildReport(
                (int) ($validated['days'] ?? 7),
                [
                    'repository_path' => $validated['repository_path'] ?? null,
                    'machine_key' => $validated['machine_key'] ?? null,
                ],
            ),
        ]);
    }
}
