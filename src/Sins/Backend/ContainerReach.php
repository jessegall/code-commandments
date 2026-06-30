<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\LaravelIdioms;

final class ContainerReach extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'container-reach',
            skill: LaravelIdioms::class,
            description: "`app()`/`resolve()` reach inside a container-resolved class"
        );
    }
}
