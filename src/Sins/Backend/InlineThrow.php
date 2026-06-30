<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow;

final class InlineThrow extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'inline-throw',
            skill: GuardClausesAndFlow::class,
            description: "`?? throw` / `=== null ? …` feeding further work on the same line (inline throw mid-expression)"
        );
    }
}
