<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\RepeatedCallHelper;

final class RepeatedNamedCall extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-repeated-named-call',
            skill: RepeatedCallHelper::class,
            description: 'the same `**changes` call is built the same way with the same keyword at 2+ sites — an operation that has no name on the type it belongs to.',
            rule: 'Name a keyword call you keep writing the same way: a method on the type — `node.with_meta(payload)` — that hides the call and the construction.',
            suggestion: 'Add a method to the receiver\'s class that makes the call, and call that at every site.',
        );
    }
}
