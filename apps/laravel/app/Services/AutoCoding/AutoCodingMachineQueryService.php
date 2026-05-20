<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingMachine;
use App\Repositories\AutoCoding\Interfaces\AutoCodingMachineRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AutoCodingMachineQueryService
{
    public function __construct(
        private readonly AutoCodingMachineRepositoryInterface $machineRepository,
    ) {}

    /**
     * Return paginated local auto-coding machines for admin APIs.
     *
     * @param  int  $perPage
     * @return LengthAwarePaginator<int, AutoCodingMachine>
     */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator
    {
        return $this->machineRepository->paginateForAdmin($perPage);
    }

    /**
     * Resolve one detailed local auto-coding machine by id.
     *
     * @param  int  $machineId
     * @return AutoCodingMachine|null
     */
    public function findDetailedById(int $machineId): ?AutoCodingMachine
    {
        return $this->machineRepository->findDetailedById($machineId);
    }
}
