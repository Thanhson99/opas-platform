<?php

declare(strict_types=1);

namespace App\Repositories\AutoCoding;

use App\Models\AutoCodingMachine;
use App\Repositories\AutoCoding\Interfaces\AutoCodingMachineRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AutoCodingMachineRepository implements AutoCodingMachineRepositoryInterface
{
    /**
     * Return local auto-coding machines with related task runs for admin APIs.
     *
     * @param  int  $perPage
     * @return LengthAwarePaginator<int, AutoCodingMachine>
     */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator
    {
        return $this->baseDetailedQuery()->paginate($perPage);
    }

    /**
     * Find one detailed local auto-coding machine by id.
     *
     * @param  int  $machineId
     * @return AutoCodingMachine|null
     */
    public function findDetailedById(int $machineId): ?AutoCodingMachine
    {
        /** @var AutoCodingMachine|null $machine */
        $machine = $this->baseDetailedQuery()->find($machineId);

        return $machine;
    }

    /**
     * Build the shared detailed query for local auto-coding machine reads.
     *
     * @return Builder<AutoCodingMachine>
     */
    protected function baseDetailedQuery(): Builder
    {
        return AutoCodingMachine::query()
            ->with('taskRuns.task')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id');
    }
}
