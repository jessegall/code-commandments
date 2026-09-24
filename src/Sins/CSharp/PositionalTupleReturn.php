<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\ValueObjects;

final class PositionalTupleReturn extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-positional-tuple-return',
            skill: ValueObjects::class,
            description: 'a method that returns `(decimal, decimal, string)` — unnamed values the caller reads by position, where two of the same type can be swapped and nothing notices',
            rule: 'Return a `record` or a tuple with named slots, not a tuple the caller has to read by position.',
            suggestion: 'Declare `public sealed record Totals(decimal Net, decimal Vat, string Currency);` and return it — or at least name the slots: `(decimal Net, decimal Vat, string Currency)`.',
        );
    }
}
