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
            description: 'A wither method rebuilds the whole object by re-listing every constructor field, so adding a new field means updating every wither in the class.',
            rule: 'A wither should only say what changes: `clone($this, [\'x\' => $x])` states the intent, while re-listing every field just repeats the constructor in every wither.',
            suggestion: 'Replace `new self($this->a, $this->b, $changed)` with `clone($this, [\'c\' => $changed])` — `repent` does it for you.'
        );
    }
}
