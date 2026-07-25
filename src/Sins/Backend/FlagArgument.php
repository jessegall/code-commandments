<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\BehaviourPerMethod;

final class FlagArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'flag-argument',
            skill: BehaviourPerMethod::class,
            description: "a method whose whole body branches on a `bool` parameter — two methods sharing one name",
            rule: "Split a method whose body is one branch on a flag into two NAMED methods — never make a call site say `true`.",
            suggestion: "name each half for what it does (`renderCompact()` / `renderFull()`), with any shared middle as a private method both call",
        );
    }
}
