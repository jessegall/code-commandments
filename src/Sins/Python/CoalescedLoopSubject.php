<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Flow;

final class CoalescedLoopSubject extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-coalesced-loop-subject',
            skill: Flow::class,
            description: '`for x in d.get(k, [])` / `for x in y or []` over a parameter — whether the caller handed anything over, decided in the loop header instead of stated as a guard',
            rule: 'State an absent collection at the top as a guard; don\'t bury `or []` or `.get(k, [])` in a `for` header.',
            suggestion: 'Return early when the collection is absent — or make the caller always hand one over — so the loop walks something that is there.',
        );
    }
}
