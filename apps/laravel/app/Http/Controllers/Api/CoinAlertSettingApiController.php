<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCoinAlertSettingRequest;
use App\Http\Resources\CoinAlertSettingResource;
use App\Models\CoinAlertSetting;
use Illuminate\Http\JsonResponse;

class CoinAlertSettingApiController extends Controller
{
    /**
     * Return all price alert settings for the React client.
     */
    public function index(): JsonResponse
    {
        return CoinAlertSettingResource::collection(
            CoinAlertSetting::orderBy('threshold_percent')->get()
        )->response();
    }

    /**
     * Return a single alert setting for edit screens.
     */
    public function show(int $id): JsonResponse
    {
        return (new CoinAlertSettingResource(CoinAlertSetting::findOrFail($id)))->response();
    }

    /**
     * Update an existing alert setting with validated input.
     */
    public function update(UpdateCoinAlertSettingRequest $request, int $id): JsonResponse
    {
        $setting = CoinAlertSetting::findOrFail($id);
        $setting->update($request->validated());

        return (new CoinAlertSettingResource($setting->fresh()))
            ->additional(['message' => 'Alert updated successfully.'])
            ->response();
    }

    /**
     * Toggle the active state of one alert setting.
     */
    public function toggle(int $id): JsonResponse
    {
        $setting = CoinAlertSetting::findOrFail($id);
        $setting->is_active = ! $setting->is_active;
        $setting->save();

        return (new CoinAlertSettingResource($setting))
            ->additional(['message' => 'Alert status updated successfully.'])
            ->response();
    }
}
