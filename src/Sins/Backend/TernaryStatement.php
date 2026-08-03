<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow;

final class TernaryStatement extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'ternary-statement',
            skill: GuardClausesAndFlow::class,
            description: "a bare `\$cond ? doThis() : doThat();` statement — a ternary whose value nothing reads, so it is choosing an ACTION, not a value",
            rule: "Choose an action with `if`/`else`; a ternary chooses a VALUE, so never write one whose result nothing reads.",
        );
    }
}
