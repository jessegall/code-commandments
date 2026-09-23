<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Enums;

final class EnumCaseOrChain extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-enum-case-or-chain',
            skill: Enums::class,
            description: '`s == Status.PENDING or s == Status.LATE` — a group of an enum\'s members re-derived at the call site instead of named on the enum',
            rule: 'Name a group of an enum\'s members as a method or property on the enum; don\'t re-list the members in an `or` chain at every call site.',
            suggestion: 'A property on the enum — `def is_open(self) -> bool: return self in (Status.PENDING, Status.LATE)` — and `s.is_open` at the call site.',
        );
    }
}
