<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Exceptions;

final class MessageStringRaise extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-message-string-raise',
            skill: Exceptions::class,
            description: '`raise Exception/RuntimeError("…")` — a failure that names nothing, described in prose at the raise site',
            rule: 'Raise a named exception built by a classmethod factory, never a bare `Exception` or `RuntimeError` with a message written at the raise.',
            suggestion: 'Give the failure a class of its own with a classmethod that takes the values and writes the message once — `raise NoActiveRequest.for_(name)` — so a caller can catch it by name.',
        );
    }
}
