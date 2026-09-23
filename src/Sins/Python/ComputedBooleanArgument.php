<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\PassTheObject;

final class ComputedBooleanArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-computed-boolean-argument',
            skill: PassTheObject::class,
            description: 'a method taking only bools that every caller computes from the same object — the decision re-derived at each call site',
            rule: 'Hand the method the object that its callers keep asking about, and let the method ask it directly; a bool every caller computes the same way is a decision made in the wrong place.',
            suggestion: 'Take the object (`text(order)`) and read `order.status`/`order.total` inside, so the rule lives once.',
        );
    }
}
