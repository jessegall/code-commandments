<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\LaravelIdioms;

final class DeadEventWiring extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'dead-event-wiring',
            skill: LaravelIdioms::class,
            description: 'An `Event::listen` on an event class no live code path can fire — a listener chain that dead-ends but reads as live wiring',
            rule: 'A listener exists to answer a dispatch. When the last dispatcher goes, the listener goes with it.',
            suggestion: 'Delete the registration (and the listener, if nothing else reaches it) — or restore the dispatch the listener was waiting for.'
        );
    }
}
