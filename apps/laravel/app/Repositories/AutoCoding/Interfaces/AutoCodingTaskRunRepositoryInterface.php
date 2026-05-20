<?php

declare(strict_types=1);

namespace App\Repositories\AutoCoding\Interfaces;

use App\Models\AutoCodingTaskRun;

interface AutoCodingTaskRunRepositoryInterface
{
    /**
     * Find one detailed local auto-coding task run by id.
     *
     * @param  int  $runId
     * @return AutoCodingTaskRun|null
     */
    public function findDetailedById(int $runId): ?AutoCodingTaskRun;
}
