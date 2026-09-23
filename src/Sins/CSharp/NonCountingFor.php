<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Flow;

final class NonCountingFor extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-non-counting-for',
            skill: Flow::class,
            description: 'a `for` loop whose step assigns the next item instead of moving a counter — a walk written as a count',
            rule: 'Use `for` only to count; walk with a `while` loop, or let the type hand out its items as an `IEnumerable<T>`.',
            suggestion: 'Rewrite it as `while (link != null) { …; link = link.Next; }`, or give the type an iterator (`yield return`) and loop over it with `foreach`.',
        );
    }
}
