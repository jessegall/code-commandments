<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Flow;

final class NestedConditional extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-nested-conditional',
            skill: Flow::class,
            description: '`a if x else b if y else c` — a conditional expression inside another\'s branch, a branching decision folded into one line',
            rule: 'Unfold a conditional expression nested in another\'s branch into a `match`, a lookup or guard clauses; don\'t chain `… if … else … if … else …`.',
            suggestion: 'A `match` over the subject, a dict lookup for a table of values, or a small function whose guards return early.',
        );
    }
}
