<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Enums;

final class InArrayMirrorsEnum extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-in-array-mirrors-enum',
            skill: Enums::class,
            description: '`new[] { "paid", "refunded" }.Contains(status)` or `status is "paid" or "refunded"` — a list of strings that repeats the members of an enum the code already has',
            rule: 'Parse the string into the enum once and test the enum; don\'t test it against a list of strings that repeats the enum\'s members.',
            suggestion: 'Read it with `Enum.TryParse<Status>(value, ignoreCase: true, out var status)` where it comes in, then ask the enum (`status.IsSettled()`).',
        );
    }
}
