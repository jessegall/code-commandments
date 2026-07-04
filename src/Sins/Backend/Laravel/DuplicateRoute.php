<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\RouteActions;

final class DuplicateRoute extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'duplicate-route',
            skill: RouteActions::class,
            description: "Two route registrations of the same verb bind different URLs to the SAME `[Controller, method]` — two names for one handler (invokable single-action controllers, commonly aliased to several canonical URLs, are exempt)",
            rule: "Register a `[Controller, method]` action once; a second URL onto the same handler is a maintenance trap (names, middleware, constraints drift). An invokable controller mapped to several canonical URLs is fine.",
            suggestion: "Keep one route; if a second URL is truly needed, make it a redirect, or an invokable controller."
        );
    }
}
