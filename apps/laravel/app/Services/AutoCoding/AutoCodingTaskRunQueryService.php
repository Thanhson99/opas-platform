<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingTaskRun;
use App\Repositories\AutoCoding\Interfaces\AutoCodingTaskRunRepositoryInterface;

class AutoCodingTaskRunQueryService
{
    public function __construct(
        private readonly AutoCodingTaskRunRepositoryInterface $taskRunRepository,
    ) {}

    /**
     * Resolve one detailed local auto-coding task run by id.
     *
     * @param  int  $runId
     * @return AutoCodingTaskRun|null
     */
    public function findDetailedById(int $runId): ?AutoCodingTaskRun
    {
        return $this->taskRunRepository->findDetailedById($runId);
    }
}
