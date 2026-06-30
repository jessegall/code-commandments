<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\LaravelIdioms;

final class MassUpdateAtCallSite extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'mass-update-at-call-site',
            skill: LaravelIdioms::class,
            description: "Bare `\$model->update([...])` mass-array update at a call site"
        );
    }
}
