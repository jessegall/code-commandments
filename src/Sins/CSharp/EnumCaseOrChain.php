<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Enums;

final class EnumCaseOrChain extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-enum-case-or-chain',
            skill: Enums::class,
            description: '`s == Status.Paid || s == Status.Refunded` (or `s is Status.Paid or Status.Refunded`) — a group of enum cases tested by hand at the call site',
            rule: 'Give a group of enum cases a name on the enum — an extension method with a `switch` — instead of listing the cases wherever the group is needed.',
            suggestion: 'Add `public static bool IsSettled(this Status status) => status switch { Status.Paid or Status.Refunded => true, _ => false };` beside the enum, and call `s.IsSettled()`.',
        );
    }
}
