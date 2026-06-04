<?php

declare(strict_types=1);

namespace App\Repositories\AutoCoding;

use App\Models\AutoCodingMachine;
use App\Repositories\AutoCoding\Interfaces\AutoCodingMachineRepositoryInterface;
use App\Support\RepositoryPathMatcher;
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
     * Find the latest detailed local auto-coding machine.
     *
     * @return AutoCodingMachine|null
     */
    public function findLatestDetailed(): ?AutoCodingMachine
    {
        /** @var AutoCodingMachine|null $machine */
        $machine = $this->baseDetailedQuery()->first();

        return $machine;
    }

    /**
     * Return machines that expose one repository path, newest heartbeat first.
     *
     * @param  string  $repositoryPath
     * @return list<AutoCodingMachine>
     */
    public function getLatestForRepository(string $repositoryPath): array
    {
        if ($this->requiresFullRepositoryMatcher($repositoryPath)) {
            return $this->getLatestForRepositoryUsingFullMatcher($repositoryPath);
        }

        $machines = $this->getLatestRepositoryPrefilteredMachines($repositoryPath);
        $filteredMachines = $this->filterMachinesForRepository($machines, $repositoryPath);

        if ($filteredMachines !== []) {
            return $filteredMachines;
        }

        return $this->getLatestForRepositoryUsingFullMatcher($repositoryPath);
    }

    /**
     * Return machines using a full normalized matcher scan.
     *
     * @param  string  $repositoryPath
     * @return list<AutoCodingMachine>
     */
    protected function getLatestForRepositoryUsingFullMatcher(string $repositoryPath): array
    {
        $machines = AutoCodingMachine::query()
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();

        return $this->filterMachinesForRepository($machines, $repositoryPath);
    }

    /**
     * Determine whether exact SQL variants are not enough for safe candidate ordering.
     *
     * @param  string  $repositoryPath
     * @return bool
     */
    protected function requiresFullRepositoryMatcher(string $repositoryPath): bool
    {
        return RepositoryPathMatcher::isWindowsStyle($repositoryPath);
    }

    /**
     * Return likely machine candidates using exact repository SQL variants.
     *
     * @param  string  $repositoryPath
     * @return \Illuminate\Support\Collection<int, AutoCodingMachine>
     */
    protected function getLatestRepositoryPrefilteredMachines(string $repositoryPath): \Illuminate\Support\Collection
    {
        $repositoryPathVariants = RepositoryPathMatcher::variantsForExactMatch([$repositoryPath]);

        return AutoCodingMachine::query()
            ->where(function (Builder $query) use ($repositoryPathVariants): void {
                $query->whereIn('repository_path', $repositoryPathVariants)
                    ->orWhereNotNull('workspace_bindings');
            })
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Filter machine candidates by repository binding using normalized path matching.
     *
     * @param  \Illuminate\Support\Collection<int, AutoCodingMachine>  $machines
     * @param  string  $repositoryPath
     * @return list<AutoCodingMachine>
     */
    protected function filterMachinesForRepository(\Illuminate\Support\Collection $machines, string $repositoryPath): array
    {
        /** @var list<AutoCodingMachine> $filteredMachines */
        $filteredMachines = $machines
            ->filter(fn (AutoCodingMachine $machine): bool => $this->machineMatchesRepository($machine, $repositoryPath))
            ->values()
            ->all();

        return $filteredMachines;
    }

    /**
     * Determine whether one machine record binds to a repository path.
     *
     * @param  AutoCodingMachine  $machine
     * @param  string  $repositoryPath
     * @return bool
     */
    protected function machineMatchesRepository(AutoCodingMachine $machine, string $repositoryPath): bool
    {
        if (is_string($machine->repository_path) && RepositoryPathMatcher::matches($machine->repository_path, $repositoryPath)) {
            return true;
        }

        $workspaceBindings = $machine->workspace_bindings ?? [];

        foreach ($workspaceBindings as $binding) {
            if (is_string($binding['repository_path'] ?? null) && RepositoryPathMatcher::matches($binding['repository_path'], $repositoryPath)) {
                return true;
            }
        }

        return false;
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
