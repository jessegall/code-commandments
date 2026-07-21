<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\RouteActions;

final class DanglingRouteName extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'dangling-route-name',
            skill: RouteActions::class,
            description: "A `route('x')` lookup naming a route no registration mints — a stringly cross-reference that only fails at runtime, as a 500",
            rule: 'The route-name vocabulary is a CLOSED set: every name looked up must be a name some route registers. Renaming a route means renaming its references in the same breath.',
            suggestion: "Point the lookup at the registered name, or register the route the name promises."
        );
    }
}
