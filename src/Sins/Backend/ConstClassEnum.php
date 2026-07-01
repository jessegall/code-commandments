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
            description: "A class of 2+ scalar `const`s and nothing else — a closed set hand-rolled as constants instead of a native enum",
            rule: "Seal a closed set of values as a native backed enum, not a class of scalar `const`s or loose strings.",
            suggestion: "A native `enum X: string` with the values as cases."
        );
    }
}
