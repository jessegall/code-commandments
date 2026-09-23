<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\TellDontAsk;

final class TypeSwitch extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-type-switch',
            skill: TellDontAsk::class,
            description: 'an `isinstance` ladder over classes the codebase owns — the value is asked what it is so the caller can decide what to do.',
            rule: 'Give each type the method and call it (`shape.area()`) instead of asking a value what it is in an `isinstance` ladder.',
            suggestion: 'Declare the method on the shared base, implement it on each class, and replace the ladder with the call.',
        );
    }
}
