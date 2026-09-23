<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Absence;

final class InventedDefault extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-invented-default',
            skill: Absence::class,
            description: '`F(x ?? "")` — an empty string, `0` or `false` invented to fill an argument, or answered by a lookup helper on a miss, a stand-in the callee cannot tell from real data',
            rule: 'Never fill a value with an invented `""`, `0` or `false` on absence — handle the missing case, or make the value certain where it is born.',
            suggestion: 'Decide at the source: throw when the value must be there, or pass the absence on to a parameter typed to admit it (`T?`, `TryGetValue`). A real default (`?? "EUR"`) is a choice, not an invention.',
        );
    }
}
