<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Absence;

final class ConditionalSpread extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-conditional-spread',
            skill: Absence::class,
            description: '`**({"k": v} if v else {})` / `*([x] if x else [])` — an entry spread in only when present, the absence decided in a conditional into an empty collection',
            rule: 'Don\'t spread a conditional into an empty collection to include an entry; give the target a factory that drops what is absent, and pass the value by name.',
            suggestion: 'A `@classmethod` factory — `Payload.of(note=note)` — whose body drops `None` keyword arguments, so an absent value simply vanishes with no conditional.',
        );
    }
}
