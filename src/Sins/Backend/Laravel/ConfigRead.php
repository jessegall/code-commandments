<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\LaravelIdioms;

final class ConfigRead extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'config-read',
            skill: LaravelIdioms::class,
            description: "`config('…')` read inside a class",
            rule: "Inject a typed config object; never read `config('…')` inside a class.",
            suggestion: "Inject a typed config value object."
        );
    }
}
