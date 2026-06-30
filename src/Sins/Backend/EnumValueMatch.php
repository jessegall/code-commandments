<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour;

final class EnumValueMatch extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'enum-value-match',
            skill: EnumsWithBehaviour::class,
            description: "`match`/`switch` over an enum's `->value` at a call site (homeless method)"
        );
    }
}
