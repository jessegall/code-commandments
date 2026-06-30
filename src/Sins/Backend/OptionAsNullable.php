<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class OptionAsNullable extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'option-as-nullable',
            skill: Absence::class,
            description: "`Option<T>` used as a nullable costume — `?Option`, `Option | null`, `unwrapOr(null)`"
        );
    }
}
