<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\RouteActions;

final class RouteDelegatesToController extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'route-delegates-to-controller',
            skill: RouteActions::class,
            description: "A route action forwards to ANOTHER controller's action (`return \$this->otherController->action(...)`) — a redundant entry point onto an operation that already has one",
            rule: "A route action delegates INTO the domain (a service/action class), never sideways into another controller.",
            suggestion: "Extract the shared work into a service both routes call, or point the route at the real action and delete the wrapper."
        );
    }
}
