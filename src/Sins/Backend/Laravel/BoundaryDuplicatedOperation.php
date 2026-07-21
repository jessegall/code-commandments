<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\RouteActions;

final class BoundaryDuplicatedOperation extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'boundary-duplicated-operation',
            skill: RouteActions::class,
            description: 'The same domain operation hand-rolled at two DIFFERENT entry boundaries (a console command and an MCP tool, a controller and a command) — one operation with two implementations that drift',
            rule: 'One operation, one implementation. A boundary translates its own protocol and calls the shared application service; it does not re-spell the operation.',
            suggestion: 'Hoist the shared sequence into one application service and have both faces call it.'
        );
    }
}
