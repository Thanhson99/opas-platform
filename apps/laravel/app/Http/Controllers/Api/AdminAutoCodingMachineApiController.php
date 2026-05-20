<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAutoCodingMachinesRequest;
use App\Http\Requests\StoreAutoCodingMachineHeartbeatRequest;
use App\Http\Resources\AutoCodingMachineResource;
use App\Services\AutoCoding\AutoCodingMachineQueryService;
use App\Services\AutoCoding\LocalMachineService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AdminAutoCodingMachineApiController extends Controller
{
    public function __construct(
        private readonly AutoCodingMachineQueryService $machineQueryService,
        private readonly LocalMachineService $localMachineService,
    ) {}

    /**
     * Return the local auto-coding machines shown in the admin automation view.
     *
     * @param  ListAutoCodingMachinesRequest  $request
     * @return AnonymousResourceCollection
     */
    public function index(ListAutoCodingMachinesRequest $request): AnonymousResourceCollection
    {
        /** @var array{per_page?:int} $validated */
        $validated = $request->validated();

        return AutoCodingMachineResource::collection(
            $this->machineQueryService->paginateForAdmin(
                (int) ($validated['per_page'] ?? 10),
            )
        );
    }

    /**
     * Return one local auto-coding machine with its latest run details.
     *
     * @param  int  $id
     * @return AutoCodingMachineResource
     */
    public function show(int $id): AutoCodingMachineResource
    {
        $machine = $this->machineQueryService->findDetailedById($id);

        abort_if($machine === null, Response::HTTP_NOT_FOUND, 'Local auto-coding machine not found.');

        return new AutoCodingMachineResource($machine);
    }

    /**
     * Persist one heartbeat reported by a local auto-coding machine.
     *
     * @param  StoreAutoCodingMachineHeartbeatRequest  $request
     * @return AutoCodingMachineResource
     */
    public function heartbeat(StoreAutoCodingMachineHeartbeatRequest $request): AutoCodingMachineResource
    {
        /** @var array{
         *   machine_key:string,
         *   hostname:string,
         *   operating_system:string,
         *   repository_path?:string,
         *   metadata?:array<string, mixed>
         * } $validated
         */
        $validated = $request->validated();

        $machine = $this->localMachineService->recordHeartbeat($validated);

        return new AutoCodingMachineResource(
            $this->machineQueryService->findDetailedById($machine->id) ?? $machine
        );
    }
}
