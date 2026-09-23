<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\ValueObjects;

final class HandRolledReplace extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-hand-rolled-replace',
            skill: ValueObjects::class,
            description: '`return Order(self.number, self.lines, self.note, "paid")` in a dataclass — every field re-listed to change one',
            rule: 'Derive a changed dataclass with `dataclasses.replace`, naming only what changes; don\'t re-list every field by hand.',
            suggestion: '`return replace(self, status="paid")` — a field added later needs no change here.',
        );
    }
}
