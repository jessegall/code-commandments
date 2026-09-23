<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Enums;

final class ConstantClassEnum extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-constant-class-enum',
            skill: Enums::class,
            description: 'a class that is nothing but `PENDING = "pending"` constants — a closed set of values written out by hand instead of an `Enum`',
            rule: 'Seal a closed set of values as an `Enum` or `StrEnum`, so the set is a type and its cases have a home for behaviour.',
            suggestion: '`class Status(StrEnum): PENDING = "pending"` — then give the per-case knowledge methods on the enum.',
        );
    }
}
