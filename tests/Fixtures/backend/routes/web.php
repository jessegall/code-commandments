<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Shop\Http\Controllers\Delegated\AdminExportController;
use Shop\Http\Controllers\Delegated\ExportController;
use Shop\Http\Controllers\Delegated\KioskLabelController;
use Shop\Http\Controllers\Delegated\LabelController;
use Shop\Http\Controllers\Delegated\PublicReviewController;
use Shop\Http\Controllers\Delegated\ReviewController;

// A route file is class-less script scope: there is no object and no constructor
// to inject into, so facades are the idiom here. FacadeCallDetector must NOT flag
// any of these — neither the top-level calls nor the ones inside route closures.

Route::get('/products', function () {
    return Cache::remember('products', 60, static fn (): array => []);
});

Route::post('/cart/{id}', function (string $id) {
    Cache::forget("cart:{$id}");

    return Route::current();
});

Cache::put('routes.warmed', true);

// Each operation's real controller has its own route; the wrapper controllers register a SECOND route
// onto the same operation — the redundant-entry-point smell RouteDelegatesToController flags.
Route::post('/export/{id}', [ExportController::class, 'run']);
Route::post('/admin/export/{id}', [AdminExportController::class, 'run']);
Route::post('/labels/{sku}', [LabelController::class, 'print']);
Route::post('/kiosk/labels/{sku}', [KioskLabelController::class, 'print']);
Route::post('/reviews/{reviewId}', [ReviewController::class, 'publish']);
Route::post('/public/reviews/{reviewId}', [PublicReviewController::class, 'publish']);

// The route-name vocabulary DanglingRouteNameDetector checks references against. A name registered
// here is a name `route('…')` may look up; anything else names a route that does not exist. Closure
// actions keep these registrations out of the duplicate-action rules.
Route::get('/dashboard', fn (): string => 'dashboard')->name('dashboard');
Route::name('reports.')->group(function () {
    Route::get('/reports/daily', fn (): string => 'daily')->name('daily');
});
Route::group(['as' => 'kiosk.'], function () {
    Route::get('/kiosk/home', fn (): string => 'home')->name('home');
});
