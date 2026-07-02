<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\PhpTypes;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class OptionAsNullable extends Sin implements RequiresComposerPackage
{
    public function __construct()
    {
        parent::__construct(
            name: 'option-as-nullable',
            skill: Absence::class,
            description: "`Option<T>` used as a nullable costume — `?Option`, `Option | null`, `unwrapOr(null)`",
            rule: "Use `Option` as a real option (`some`/`none`/`match`); never `?Option`/`Option | null`/`unwrapOr(null)`.",
            suggestion: "Wrap at the seam with `Option::fromNullable(\$x)`, then consume with `match`/`unwrapOr`."
        );
    }

    public function requiredComposerPackage(): string
    {
        return 'jessegall/php-types';
    }
}
