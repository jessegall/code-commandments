<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow;

final class IfElseLadder extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'if-else-ladder',
            skill: GuardClausesAndFlow::class,
            description: "if/elseif ladder of 4+ branches (should be match/dispatch)"
        );
    }
}
