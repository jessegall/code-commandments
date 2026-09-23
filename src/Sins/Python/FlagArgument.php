<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\BehaviourPerMethod;

final class FlagArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-flag-argument',
            skill: BehaviourPerMethod::class,
            description: 'a function whose whole body branches on a `bool` parameter — or on whether an optional one was given — two functions sharing one name',
            rule: "Split a function whose body is one branch on a flag into two NAMED functions — never make a call say `True`, and never widen a required parameter to `X | None = None` so that leaving it out means 'all of them'.",
            suggestion: 'Name each half for what it does (`render_compact()` / `render_full()`), with any shared middle as a private function both call.',
        );
    }
}
