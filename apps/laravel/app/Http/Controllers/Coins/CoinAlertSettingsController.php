<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coins;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCoinAlertSettingRequest;
use App\Models\CoinAlertSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class CoinAlertSettingsController
 *
 * Controller for managing coin alert settings, including listing, editing, updating,
 * and toggling their activation status.
 */
class CoinAlertSettingsController extends Controller
{
    /**
     * Display a list of all coin alert settings.
     */
    public function index(): View
    {
        $settings = CoinAlertSetting::orderBy('threshold_percent')->get();

        return view('coins.price-alert.index', compact('settings'));
    }

    /**
     * Show the edit form for a specific alert setting.
     *
     * @param  int  $id  Alert setting identifier.
     */
    public function edit(int $id): View
    {
        $setting = CoinAlertSetting::findOrFail($id);

        return view('coins.price-alert.edit', compact('setting'));
    }

    /**
     * Update the specified alert setting in the database.
     *
     * @param  UpdateCoinAlertSettingRequest  $request  Validated update request.
     * @param  int  $id  Alert setting identifier.
     */
    public function update(UpdateCoinAlertSettingRequest $request, int $id): RedirectResponse
    {
        DB::beginTransaction();

        try {
            $setting = CoinAlertSetting::findOrFail($id);
            $setting->update($request->validated());

            DB::commit();

            return redirect()
                ->route('coin-alert-settings.index')
                ->with('success', 'Alert setting updated successfully.');
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update alert setting.', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['update_error' => 'Failed to update the alert setting. Please try again.']);
        }
    }

    /**
     * Toggle the active status for a specific alert setting.
     *
     * @param  int  $id  Alert setting identifier.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $setting = CoinAlertSetting::findOrFail($id);
            $setting->is_active = ! $setting->is_active;
            $setting->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'is_active' => $setting->is_active,
                'message' => 'Status updated successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to toggle alert setting status.', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status. Please try again.',
            ], 500);
        }
    }
}
