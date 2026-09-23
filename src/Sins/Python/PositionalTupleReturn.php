<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\ValueObjects;

final class PositionalTupleReturn extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-positional-tuple-return',
            skill: ValueObjects::class,
            description: '`return net, vat, currency` — a bundle of different things the caller must unpack by position, where a reordering breaks silently',
            rule: 'Return a named result — a frozen dataclass or a NamedTuple — not a tuple of different things the caller unpacks by position.',
            suggestion: 'A small `@dataclass(frozen=True)` (or `NamedTuple`) whose fields name each slot.',
        );
    }
}
