<?php

namespace Shop\Http\View;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\DanglingRouteName;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Navigation built from route NAMES. The vocabulary is closed — `routes/web.php` registers
 * `dashboard`, `reports.daily` and `kiosk.home` — so a lookup naming anything else points at a route
 * that does not exist, and only fails when a user walks that path, as a 500.
 */
final class DashboardLinks
{
    /**
     * A misspelt name buried in a menu array: every other entry resolves, so the page renders until a
     * user clicks THIS one.
     */
    #[Sinful(DanglingRouteName::class)]
    public function menu(): array
    {
        return [
            ['label' => 'Home', 'href' => route('dashboard')],
            ['label' => 'Overview', 'href' => route('dashbord')],
        ];
    }

    /**
     * Righteous: the name is exactly the one the route registers.
     */
    #[Righteous(DanglingRouteName::class)]
    public function home(): string
    {
        return route('dashboard');
    }
}
