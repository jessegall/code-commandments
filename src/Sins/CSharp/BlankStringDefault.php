<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Absence;

final class BlankStringDefault extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-blank-string-default',
            skill: Absence::class,
            description: 'a `string` parameter or property defaulted to `""` and then checked with `== ""` or `string.IsNullOrEmpty` — the blank is being used to mean "missing"',
            rule: 'If a value can be missing, say so in its type with `string?`; don\'t default it to `""` and then check for the blank.',
            suggestion: 'Make it `string? note = null` and check `note is null`, so nobody has to know that `""` means "not given".',
        );
    }
}
