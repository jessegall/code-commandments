<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow;

final class CoalescedLoopSubject extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'coalesced-loop-subject',
            skill: GuardClausesAndFlow::class,
            description: "`foreach (\$x[\$k] ?? [] as …)` — the absence check buried in the loop header instead of stated as a guard",
            rule: "State an absent collection at the top as a guard (early return); don't bury `?? []` in a `foreach` header.",
            suggestion: "An early `return` when the collection is absent, so the loop iterates something that is THERE.",
        );
    }
}
