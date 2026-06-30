<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour;

final class ConstClassEnum extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'const-class-enum',
            skill: EnumsWithBehaviour::class,
            description: "Closed set as raw string literals / a `const` class of scalars (not a native enum)"
        );
    }
}
