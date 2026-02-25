<?php

use App\Http\Controllers\Coins\CoinAlertSettingsController;
use App\Http\Controllers\Coins\CoinController;
use App\Http\Controllers\Coins\FavoriteCoinController;
use App\Http\Controllers\Coins\FeedKeywordController;
use App\Http\Controllers\Stocks\FavoriteStockController;
use App\Http\Controllers\Stocks\StockController;
use App\Http\Controllers\VideoAutomation\TrendingVideoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Welcome page
Route::view('/', 'welcome');

// Coin module routes (all under 'coins' prefix)
Route::prefix('coins')->name('coins.')->group(function () {

    // CoinController routes
    Route::controller(CoinController::class)->group(function () {
        Route::get('/', 'index')->name('index');            // List top coins
        Route::get('show/{symbol}', 'show')->name('show');  // Show coin detail
    });

    // FavoriteCoinController routes
    Route::controller(FavoriteCoinController::class)->group(function () {
        Route::post('favorites/toggle', 'favoritesToggle')->name('favorites.toggle'); // AJAX toggle favorite
    });

    // FeedKeywordController routes
    Route::prefix('feed-keywords')->name('feed-keywords.')->controller(FeedKeywordController::class)->group(function () {
        Route::get('/', 'index')->name('index');            // Show keywords
        Route::post('store', 'store')->name('store');       // Create keyword
        Route::post('destroy', 'destroy')->name('destroy'); // Delete keyword
    });

    // CoinAlertSettingsController routes
    Route::prefix('price-alert-settings')->name('price-alert-settings.')->controller(CoinAlertSettingsController::class)->group(function () {
        Route::get('/', 'index')->name('index');            // Show all price alert settings
        Route::get('/{id}/edit', 'edit')->name('edit');     // Show edit form
        Route::put('/{id}', 'update')->name('update');      // Update alert
        Route::patch('/{id}/toggle', 'toggleStatus')->name('toggle'); // Toggle on/off
    });

});

// Stock module routes (all under 'stocks' prefix)
Route::prefix('stocks')->name('stocks.')->group(function () {

    // StockController routes
    Route::controller(StockController::class)->group(function () {
        Route::get('/', 'index')->name('index'); // List Vietnam stocks
    });

    // FavoriteStockController routes
    Route::controller(FavoriteStockController::class)->group(function () {
        Route::post('favorites/add', 'favoritesAdd')->name('favorites.add'); // AJAX add favorite
        Route::post('favorites/remove', 'favoritesRemove')->name('favorites.remove'); // AJAX remove favorite
        Route::post('favorites/toggle', 'favoritesToggle')->name('favorites.toggle'); // AJAX toggle favorite
    });
});

// Video Automation routes
Route::prefix('video-automation')
    ->name('video-automation.')
    ->controller(TrendingVideoController::class)
    ->group(function () {
        Route::get('trending', 'index')->name('trending.index'); // List top trending videos
    });

// Fallback route: catch all undefined URLs
// Redirects to /404 page but avoids redirect loop
Route::fallback(function () {
    return redirect('/404');
});

// Status code 404 ensures proper HTTP response
Route::get('/404', function () {
    return response()->view('errors.404', [], 404);
});
