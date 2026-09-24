<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\RepeatedCallHelper;

final class RepeatedGuard extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-repeated-guard',
            skill: RepeatedCallHelper::class,
            description: 'the same compound condition — `order.Paid && !order.Cancelled` — written at two or more sites, a question with no name',
            rule: 'Name a compound condition asked in more than one place once, on the type it is about, and ask it by that name.',
            suggestion: 'Add `public bool IsShippable => Paid && !Cancelled;` to the type and write `if (order.IsShippable)` at every site.',
        );
    }
}
