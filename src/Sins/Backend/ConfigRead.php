<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\LaravelIdioms;

final class ConfigRead extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'config-read',
            skill: LaravelIdioms::class,
            description: "`config('…')` read inside a class"
        );
    }
}
