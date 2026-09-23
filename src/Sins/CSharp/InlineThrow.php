<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Flow;

final class InlineThrow extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-inline-throw',
            skill: Flow::class,
            description: 'a `?? throw` inside a call\'s argument or in front of a member call — the check that stops the method is hidden in the middle of the work',
            rule: 'Check at the top and throw there; don\'t hide a `?? throw` inside the expression that does the work.',
            suggestion: 'Pull it out into its own line first — `var given = name ?? throw new …;` or an `is null` check with a throw — then do the work with the checked value.',
        );
    }
}
