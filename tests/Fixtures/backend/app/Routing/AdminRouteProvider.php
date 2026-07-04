<?php

namespace Shop\Routing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicateRoute;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Registers the admin routes. Two GET routes bind different URLs to the same
 * `ReportViewController::show` — the same handler under two names.
 */
final class AdminRouteProvider extends ServiceProvider
{
    #[Sinful(DuplicateRoute::class)]
    public function map(): void
    {
        Route::get('/admin/report', [ReportViewController::class, 'show'])->name('admin.report');
        Route::get('/admin/reports/latest', [ReportViewController::class, 'show'])->name('admin.report.latest');
    }

    public function prefix(): string
    {
        return 'admin';
    }

    public function guard(int $level): string
    {
        return match (true) {
            $level >= 9 => 'root',
            $level >= 5 => 'manager',
            default => 'staff',
        };
    }

    public function label(string $section): string
    {
        return 'Admin · ' . ucfirst($section);
    }
}
