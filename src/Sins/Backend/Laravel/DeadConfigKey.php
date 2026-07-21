<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\LaravelIdioms;

final class DeadConfigKey extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'dead-config-key',
            skill: LaravelIdioms::class,
            description: 'A config key nothing reads — dead surface left behind by a deleted feature, which new code may wrongly adopt',
            rule: 'Config is an interface: every key exists because something reads it. When the last reader goes, the key goes with it.',
            suggestion: 'Delete the key (and its env var), or restore the reader the feature lost.'
        );
    }
}
