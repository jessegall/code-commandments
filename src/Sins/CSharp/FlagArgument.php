<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\BehaviourPerMethod;

final class FlagArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-flag-argument',
            skill: BehaviourPerMethod::class,
            description: 'a method whose whole body branches on a `bool` parameter — `if (compact) … else …` — two methods sharing one name',
            rule: 'Split a method a parameter chooses between into two methods, each named for what it does.',
            suggestion: '`Render(order, bool compact)` becomes `RenderCompact(order)` and `RenderFull(order)`, with anything they share in a private method both call.',
        );
    }
}
