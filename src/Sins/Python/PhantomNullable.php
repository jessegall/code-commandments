<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\TypeHonesty;

final class PhantomNullable extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-phantom-nullable',
            skill: TypeHonesty::class,
            description: 'a field annotated `X | None` that every read assumes is there and none guards — a `None` the design never has',
            rule: 'If a field is used as present everywhere, its type says so: make it required, and fail at construction on a real miss.',
            suggestion: 'Drop the `| None` and the `= None` default, and make every constructor hand the value over.',
        );
    }
}
