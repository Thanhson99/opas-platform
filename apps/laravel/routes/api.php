<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CoinAlertSettingApiController;
use App\Http\Controllers\Api\CoinApiController;
use App\Http\Controllers\Api\FavoriteCoinApiController;
use App\Http\Controllers\Api\FavoriteStockApiController;
use App\Http\Controllers\Api\FeedKeywordApiController;
use App\Http\Controllers\Api\StockApiController;
use App\Http\Controllers\Api\TrendingVideoApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function (): void {
    Route::prefix('auth')->middleware('web')->name('api.auth.')->group(function (): void {
        Route::post('/register', [AuthApiController::class, 'register'])->name('register');
        Route::post('/login', [AuthApiController::class, 'login'])->name('login');
        Route::middleware('auth')->group(function (): void {
            Route::get('/me', [AuthApiController::class, 'me'])->name('me');
            Route::post('/logout', [AuthApiController::class, 'logout'])->name('logout');
        });
    });

    Route::prefix('coins')->name('api.coins.')->group(function (): void {
        Route::get('/', [CoinApiController::class, 'index'])->name('index');
        Route::get('/{symbol}', [CoinApiController::class, 'show'])->name('show');
        Route::prefix('favorites')->name('favorites.')->group(function (): void {
            Route::middleware(['web', 'auth'])->group(function (): void {
                Route::put('/{symbol}', [FavoriteCoinApiController::class, 'store'])->name('store');
                Route::delete('/{symbol}', [FavoriteCoinApiController::class, 'destroy'])->name('destroy');
            });
        });

        Route::prefix('keywords')->name('keywords.')->group(function (): void {
            Route::get('/', [FeedKeywordApiController::class, 'index'])->name('index');
            Route::middleware(['web', 'auth'])->group(function (): void {
                Route::post('/', [FeedKeywordApiController::class, 'store'])->name('store');
                Route::delete('/{id}', [FeedKeywordApiController::class, 'destroy'])
                    ->whereNumber('id')
                    ->name('destroy');
            });
        });

        Route::prefix('alerts')->name('alerts.')->group(function (): void {
            Route::get('/', [CoinAlertSettingApiController::class, 'index'])->name('index');
            Route::get('/{id}', [CoinAlertSettingApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::middleware(['web', 'auth'])->group(function (): void {
                Route::put('/{id}', [CoinAlertSettingApiController::class, 'update'])->whereNumber('id')->name('update');
                Route::patch('/{id}/toggle', [CoinAlertSettingApiController::class, 'toggle'])->whereNumber('id')->name('toggle');
            });
        });
    });
    Route::prefix('stocks')->name('api.stocks.')->group(function (): void {
        Route::get('/', [StockApiController::class, 'index'])->name('index');
        Route::prefix('favorites')->name('favorites.')->group(function (): void {
            Route::middleware(['web', 'auth'])->group(function (): void {
                Route::put('/{symbol}', [FavoriteStockApiController::class, 'store'])->name('store');
                Route::delete('/{symbol}', [FavoriteStockApiController::class, 'destroy'])->name('destroy');
            });
        });
    });

    Route::prefix('videos')->name('api.videos.')->group(function (): void {
        Route::get('/trending', [TrendingVideoApiController::class, 'index'])->name('trending.index');
    });
});
