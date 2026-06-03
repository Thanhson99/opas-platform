<?php

declare(strict_types=1);

namespace App\Repositories\AutoCoding\Interfaces;

use App\Models\AutoCodingMachine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AutoCodingMachineRepositoryInterface
{
    /**
     * Return local auto-coding machines with related task runs for admin APIs.
     *
     * @param  int  $perPage
     * @return LengthAwarePaginator<int, AutoCodingMachine>
     */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator;

    /**
     * Find one detailed local auto-coding machine by id.
     *
     * @param  int  $machineId
     * @return AutoCodingMachine|null
     */
    public function findDetailedById(int $machineId): ?AutoCodingMachine;

    /**
     * Find the latest detailed local auto-coding machine.
     *
     * @return AutoCodingMachine|null
     */
    public function findLatestDetailed(): ?AutoCodingMachine;
}
