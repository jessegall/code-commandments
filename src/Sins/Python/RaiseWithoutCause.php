<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Exceptions;

final class RaiseWithoutCause extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-raise-without-cause',
            skill: Exceptions::class,
            description: '`raise Other(...)` inside an `except` block with no `from` — the failure being handled left as an implicit context, never named as the cause',
            rule: 'Raise a new exception from inside `except` with `from error` so the cause is kept; say `from None` when cutting it is the point.',
            suggestion: 'Bind the caught exception (`except KeyError as error:`) and raise `from error`.',
        );
    }
}
