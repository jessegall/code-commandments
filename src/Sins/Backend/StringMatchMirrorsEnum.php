<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour;

final class StringMatchMirrorsEnum extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'string-match-mirrors-enum',
            skill: EnumsWithBehaviour::class,
            description: "`match` over string literals that mirror an existing enum's cases"
        );
    }
}
