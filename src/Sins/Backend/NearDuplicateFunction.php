<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;

final class NearDuplicateFunction extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'near-duplicate-function',
            skill: FixAtTheSource::class,
            description: "Redundant methods — two+ functions with the same SHAPE differing only in names/literals (type-2 clone)"
        );
    }
}
