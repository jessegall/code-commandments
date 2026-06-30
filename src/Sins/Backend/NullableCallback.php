<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class NullableCallback extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'nullable-callback',
            skill: Absence::class,
            description: "Nullable callback normalised in the body instead of a Null Object default"
        );
    }
}
