<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour;

final class InArrayMirrorsEnum extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'in-array-mirrors-enum',
            skill: EnumsWithBehaviour::class,
            description: "`in_array(\$x, [literals])` whose literals mirror an existing enum's cases"
        );
    }
}
