<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\RouteActions;

final class DuplicateRouteAction extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'duplicate-route-action',
            skill: RouteActions::class,
            description: "Two route actions in different controllers thinly delegate to the SAME operation (`return \$this->exporter->export(...)`) — the same entry point twice",
            rule: "One operation, one entry point — collapse duplicate thin actions to a single action (or two routes onto one), with the work in the shared service.",
            suggestion: "Delete the duplicate action and point its route at the surviving one (or a redirect)."
        );
    }
}
