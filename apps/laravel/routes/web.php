<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'spa')->name('dashboard');

Route::view('/reset-password/{token}', 'spa')
    ->where('token', '.*')
    ->name('password.reset');

Route::prefix('coins')->name('coins.')->group(function (): void {
    Route::view('/', 'spa')->name('index');
    Route::view('/show/{symbol}', 'spa')->name('show');
    Route::view('/feed-keywords', 'spa')->name('feed-keywords.index');
    Route::view('/price-alert-settings', 'spa')->name('price-alert-settings.index');
    Route::view('/price-alert-settings/{id}/edit', 'spa')->name('price-alert-settings.edit');
});

Route::prefix('stocks')->name('stocks.')->group(function (): void {
    Route::view('/', 'spa')->name('index');
});

Route::prefix('video-automation')->name('video-automation.')->group(function (): void {
    Route::view('/trending', 'spa')->name('trending.index');
});

Route::view('/{any}', 'spa')
    ->where('any', '^(?!api).*$');
