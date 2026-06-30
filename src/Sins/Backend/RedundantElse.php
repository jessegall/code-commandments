<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow;

final class RedundantElse extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'redundant-else',
            skill: GuardClausesAndFlow::class,
            description: "`else` after an `if` branch that already returns/throws (redundant)"
        );
    }
}
