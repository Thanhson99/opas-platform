<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingTaskRun;

class RunArtifactService
{
    /**
     * Persist the structured artifacts emitted by one task run.
     *
     * @param  AutoCodingTaskRun  $run
     * @param  array<string, mixed>  $repositoryContext
     * @param  array<string, mixed>  $gitHubContext
     * @param  array<string, mixed>  $providerResult
     * @param  array<string, mixed>  $validationResults
     * @param  array<string, mixed>  $finalReport
     * @return void
     */
    public function persistRunArtifacts(
        AutoCodingTaskRun $run,
        array $repositoryContext,
        array $gitHubContext,
        array $providerResult,
        array $validationResults,
        array $finalReport,
    ): void {
        $artifacts = [
            [
                'type' => 'repository_snapshot',
                'label' => 'Repository Snapshot',
                'payload' => $repositoryContext,
            ],
            [
                'type' => 'github_context',
                'label' => 'GitHub Context',
                'payload' => $gitHubContext,
            ],
            [
                'type' => 'provider_result',
                'label' => 'Provider Result',
                'payload' => $providerResult,
            ],
            [
                'type' => 'validation_result',
                'label' => 'Validation Result',
                'payload' => $validationResults,
            ],
            [
                'type' => 'final_report',
                'label' => 'Final Report',
                'payload' => $finalReport,
            ],
        ];

        $run->artifacts()->createMany($artifacts);
    }
}
