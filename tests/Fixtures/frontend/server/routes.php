<?php

use Illuminate\Support\Facades\Route;

Route::get('/plans', fn () => null)->name('plans.index');
Route::get('/plans/{plan}', fn () => null)->name('plans.show');
Route::prefix('returns')->name('returns.')->group(function () {
    Route::post('/{return}/label', fn () => null)->name('label');
});
Route::get('/healthz', fn () => null);
