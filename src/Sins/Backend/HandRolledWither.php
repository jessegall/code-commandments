<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;

final class HandRolledWither extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'hand-rolled-wither',
            skill: ValueObjects::class,
            description: 'A wither rebuilds its object by re-spelling every constructor field, so each new field must be threaded through N of them',
            rule: 'A wither changes ONE thing: say only what changes. `clone($this, [\'x\' => $x])` states the intent; re-listing every field states the constructor again, N times over.',
            suggestion: 'Replace `new self($this->a, $this->b, $changed)` with `clone($this, [\'c\' => $changed])` — `repent` does it for you.'
        );
    }
}
