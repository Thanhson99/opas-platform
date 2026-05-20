<?php

declare(strict_types=1);

namespace App\Repositories\AutoCoding;

use App\Models\AutoCodingTaskRun;
use App\Repositories\AutoCoding\Interfaces\AutoCodingTaskRunRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class AutoCodingTaskRunRepository implements AutoCodingTaskRunRepositoryInterface
{
    /**
     * Find one detailed local auto-coding task run by id.
     *
     * @param  int  $runId
     * @return AutoCodingTaskRun|null
     */
    public function findDetailedById(int $runId): ?AutoCodingTaskRun
    {
        /** @var AutoCodingTaskRun|null $run */
        $run = $this->baseDetailedQuery()->find($runId);

        return $run;
    }

    /**
     * Build the shared detailed query for local auto-coding task run reads.
     *
     * @return Builder<AutoCodingTaskRun>
     */
    protected function baseDetailedQuery(): Builder
    {
        return AutoCodingTaskRun::query()
            ->with(['task', 'machine', 'artifacts'])
            ->orderByDesc('id');
    }
}
