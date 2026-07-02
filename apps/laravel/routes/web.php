<?php

use Illuminate\Support\Facades\Route;

// Core SPA entrypoints keep browser navigation on the frontend shell.
Route::view('/', 'spa')->name('dashboard');

// Auth screens are explicit SPA entrypoints so direct browser navigation stays stable.
Route::view('/login', 'spa')->name('login');
Route::view('/register', 'spa')->name('register');
Route::view('/forgot-password', 'spa')->name('password.request');
Route::view('/verify-email', 'spa')->name('verification.notice');

// Password-reset links must still land on the SPA so the frontend can own the reset flow.
Route::view('/reset-password/{token}', 'spa')
    ->where('token', '.*')
    ->name('password.reset');

// Coin screens share one SPA shell while preserving route names for feature-level navigation.
Route::prefix('coins')->name('coins.')->group(function (): void {
    Route::view('/', 'spa')->name('index');
    Route::view('/show/{symbol}', 'spa')->name('show');
    Route::view('/feed-keywords', 'spa')->name('feed-keywords.index');
    Route::view('/price-alert-settings', 'spa')->name('price-alert-settings.index');
    Route::view('/price-alert-settings/{id}/edit', 'spa')->name('price-alert-settings.edit');
});

// Stock screens currently expose a smaller SPA surface but stay in their own namespace.
Route::prefix('stocks')->name('stocks.')->group(function (): void {
    Route::view('/', 'spa')->name('index');
});

// Video automation pages stay grouped so future flows can extend the same shell cleanly.
Route::prefix('video-automation')->name('video-automation.')->group(function (): void {
    Route::view('/trending', 'spa')->name('trending.index');
});

// Realtime translate stays inside the SPA shell while capture remains owned by the local extension.
Route::view('/realtime-translate', 'spa')->name('realtime-translate.index');

// Keep a final SPA fallback for browser routes while excluding API endpoints.
Route::view('/{any}', 'spa')
    ->where('any', '^(?!api).*$');
