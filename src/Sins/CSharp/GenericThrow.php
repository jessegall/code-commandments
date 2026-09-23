<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Exceptions;

final class GenericThrow extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-generic-throw',
            skill: Exceptions::class,
            description: '`throw new Exception/InvalidOperationException("…")` — a failure that names nothing, described in prose at the throw site',
            rule: 'Throw a named exception built by a static factory, never a bare `Exception` or `InvalidOperationException` with a message written at the throw.',
            suggestion: 'Give the failure a class of its own with a static factory that takes the values and writes the message once — `throw UnknownCarrier.Named(name);` — so a caller can catch it by name.',
        );
    }
}
