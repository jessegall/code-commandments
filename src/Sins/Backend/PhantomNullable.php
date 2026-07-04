<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\TypeHonesty;

final class PhantomNullable extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'phantom-nullable',
            skill: TypeHonesty::class,
            description: "Phantom nullable — a field typed `?T` (promoted param or declared property, any class) whose value, traced through the whole program, is always read as present and NEVER guarded, so the null never happens",
            rule: "If a nullable field is assumed present everywhere its value flows and guarded nowhere, the null is a lie — make it non-nullable and let it be required, failing hard at construction on a real miss."
        );
    }
}
