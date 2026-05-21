<?php

use App\Http\Controllers\Api\AccountApiController;
use App\Http\Controllers\Api\AdminAuthProviderApiController;
use App\Http\Controllers\Api\AdminAutoCodingMachineApiController;
use App\Http\Controllers\Api\AdminAutoCodingTaskApiController;
use App\Http\Controllers\Api\AdminAutoCodingTaskRunApiController;
use App\Http\Controllers\Api\AdminUserApiController;
use App\Http\Controllers\Api\AgentAutoCodingApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\AuthProviderApiController;
use App\Http\Controllers\Api\AuthProviderOAuthApiController;
use App\Http\Controllers\Api\CoinAlertSettingApiController;
use App\Http\Controllers\Api\CoinApiController;
use App\Http\Controllers\Api\FavoriteCoinApiController;
use App\Http\Controllers\Api\FavoriteStockApiController;
use App\Http\Controllers\Api\FeedKeywordApiController;
use App\Http\Controllers\Api\StockApiController;
use App\Http\Controllers\Api\TrendingVideoApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function (): void {
    Route::prefix('auth')->middleware(['web', 'no-store'])->name('api.auth.')->group(function (): void {
        Route::get('/providers', [AuthProviderApiController::class, 'index'])->name('providers.index');
        Route::get('/providers/{key}/redirect', [AuthProviderOAuthApiController::class, 'redirect'])
            ->name('providers.redirect');
        Route::get('/providers/{key}/callback', [AuthProviderOAuthApiController::class, 'callback'])
            ->name('providers.callback');
        Route::post('/register', [AuthApiController::class, 'register'])->name('register');
        Route::post('/login', [AuthApiController::class, 'login'])->name('login');
        Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword'])->name('password.email');
        Route::post('/reset-password', [AuthApiController::class, 'resetPassword'])->name('password.update');
        Route::post('/email/verify', [AuthApiController::class, 'verifyEmailCode'])->name('verification.confirm');
        Route::post('/email/verification-notification', [AuthApiController::class, 'resendVerificationEmail'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
        Route::middleware('auth')->group(function (): void {
            Route::put('/account', [AccountApiController::class, 'update'])->name('account.update');
            Route::delete('/account/providers/{key}', [AccountApiController::class, 'unlinkProvider'])
                ->name('account.providers.destroy');
            Route::get('/me', [AuthApiController::class, 'me'])->name('me');
            Route::post('/logout', [AuthApiController::class, 'logout'])->name('logout');
        });
    });

    Route::prefix('admin/auth')->middleware(['web', 'auth', 'verified-email', 'admin', 'no-store'])->name('api.admin.auth.')->group(function (): void {
        Route::get('/providers', [AdminAuthProviderApiController::class, 'index'])->name('providers.index');
        Route::put('/providers/{key}', [AdminAuthProviderApiController::class, 'update'])->name('providers.update');
    });

    Route::prefix('admin/users')->middleware(['web', 'auth', 'verified-email', 'admin', 'no-store'])->name('api.admin.users.')->group(function (): void {
        Route::get('/', [AdminUserApiController::class, 'index'])->name('index');
        Route::put('/{id}', [AdminUserApiController::class, 'update'])->whereNumber('id')->name('update');
        Route::post('/{id}/reset-password', [AdminUserApiController::class, 'resetPassword'])->whereNumber('id')->name('reset-password');
        Route::delete('/{id}', [AdminUserApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    Route::prefix('admin/auto-coding')->middleware(['web', 'auth', 'verified-email', 'admin', 'no-store'])->name('api.admin.auto-coding.')->group(function (): void {
        Route::get('/machines', [AdminAutoCodingMachineApiController::class, 'index'])->name('machines.index');
        Route::post('/machines/heartbeat', [AdminAutoCodingMachineApiController::class, 'heartbeat'])
            ->name('machines.heartbeat');
        Route::get('/machines/{id}', [AdminAutoCodingMachineApiController::class, 'show'])
            ->whereNumber('id')
            ->name('machines.show');
        Route::post('/tasks', [AdminAutoCodingTaskApiController::class, 'store'])->name('tasks.store');
        Route::post('/tasks/claim', [AdminAutoCodingTaskApiController::class, 'claim'])->name('tasks.claim');
        Route::get('/tasks', [AdminAutoCodingTaskApiController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/{id}/status', [AdminAutoCodingTaskApiController::class, 'status'])
            ->whereNumber('id')
            ->name('tasks.status');
        Route::post('/tasks/{id}/resume', [AdminAutoCodingTaskApiController::class, 'resume'])
            ->whereNumber('id')
            ->name('tasks.resume');
        Route::get('/tasks/{id}', [AdminAutoCodingTaskApiController::class, 'show'])
            ->whereNumber('id')
            ->name('tasks.show');
        Route::get('/runs/{id}', [AdminAutoCodingTaskRunApiController::class, 'show'])
            ->whereNumber('id')
            ->name('runs.show');
        Route::get('/runs/{id}/artifacts', [AdminAutoCodingTaskRunApiController::class, 'artifacts'])
            ->whereNumber('id')
            ->name('runs.artifacts');
    });

    Route::prefix('agent/auto-coding')->middleware(['throttle:api', 'no-store'])->name('api.agent.auto-coding.')->group(function (): void {
        Route::post('/heartbeat', [AgentAutoCodingApiController::class, 'heartbeat'])->name('heartbeat');
        Route::post('/tasks/claim', [AgentAutoCodingApiController::class, 'claim'])->name('tasks.claim');
        Route::get('/tasks/{id}/status', [AgentAutoCodingApiController::class, 'status'])
            ->whereNumber('id')
            ->name('tasks.status');
    });

    Route::prefix('coins')->name('api.coins.')->group(function (): void {
        Route::get('/', [CoinApiController::class, 'index'])->name('index');
        Route::get('/{symbol}', [CoinApiController::class, 'show'])->name('show');
        Route::prefix('favorites')->name('favorites.')->group(function (): void {
            Route::middleware(['web', 'auth', 'verified-email'])->group(function (): void {
                Route::put('/{symbol}', [FavoriteCoinApiController::class, 'store'])->name('store');
                Route::delete('/{symbol}', [FavoriteCoinApiController::class, 'destroy'])->name('destroy');
            });
        });

        Route::prefix('keywords')->name('keywords.')->group(function (): void {
            Route::get('/', [FeedKeywordApiController::class, 'index'])->name('index');
            Route::middleware(['web', 'auth', 'verified-email'])->group(function (): void {
                Route::post('/', [FeedKeywordApiController::class, 'store'])->name('store');
                Route::delete('/{id}', [FeedKeywordApiController::class, 'destroy'])
                    ->whereNumber('id')
                    ->name('destroy');
            });
        });

        Route::prefix('alerts')->name('alerts.')->group(function (): void {
            Route::get('/', [CoinAlertSettingApiController::class, 'index'])->name('index');
            Route::get('/{id}', [CoinAlertSettingApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::middleware(['web', 'auth', 'verified-email'])->group(function (): void {
                Route::put('/{id}', [CoinAlertSettingApiController::class, 'update'])->whereNumber('id')->name('update');
                Route::patch('/{id}/toggle', [CoinAlertSettingApiController::class, 'toggle'])->whereNumber('id')->name('toggle');
            });
        });
    });
    Route::prefix('stocks')->name('api.stocks.')->group(function (): void {
        Route::get('/', [StockApiController::class, 'index'])->name('index');
        Route::prefix('favorites')->name('favorites.')->group(function (): void {
            Route::middleware(['web', 'auth', 'verified-email'])->group(function (): void {
                Route::put('/{symbol}', [FavoriteStockApiController::class, 'store'])->name('store');
                Route::delete('/{symbol}', [FavoriteStockApiController::class, 'destroy'])->name('destroy');
            });
        });
    });

    Route::prefix('videos')->name('api.videos.')->group(function (): void {
        Route::get('/trending', [TrendingVideoApiController::class, 'index'])->name('trending.index');
    });
});
