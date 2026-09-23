<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Flow;

final class CoalescedLoopSubject extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-coalesced-loop-subject',
            skill: Flow::class,
            description: 'a `foreach` over `items ?? []` (or `Enumerable.Empty<T>()`, or a new empty list) — the check for a missing collection is hidden in the loop header',
            rule: 'Check for a missing collection at the top with an early return, so the loop runs over something that is there.',
            suggestion: 'Write `if (items is null) { return; }` above the loop — or better, make the collection non-nullable where it comes from.',
        );
    }
}
