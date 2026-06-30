<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

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
