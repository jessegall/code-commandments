<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class NullableCollectionReturn extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'nullable-collection-return',
            skill: Absence::class,
            description: "\"Nothing\" with a natural empty form returned as `null` (`array | null` → should be `[]`)"
        );
    }
}
