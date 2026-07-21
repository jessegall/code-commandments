<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\LaravelIdioms;

final class OrphanedBinding extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'orphaned-binding',
            skill: LaravelIdioms::class,
            description: 'A container binding whose abstract nothing ever resolves — dead wiring that reads as load-bearing and survives every refactor',
            rule: 'Wiring is code: a binding exists to answer a resolve. When the last consumer goes, the binding goes with it.',
            suggestion: 'Delete the registration (and the implementation it names, if that is dead too).'
        );
    }
}
