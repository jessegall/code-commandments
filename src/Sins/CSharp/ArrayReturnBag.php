<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\ValueObjects;

final class ArrayReturnBag extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-array-return-bag',
            skill: ValueObjects::class,
            description: 'a method that returns `new Dictionary<string, object> { ["sku"] = …, ["qty"] = … }` — a record with fixed fields, handed back as a dictionary',
            rule: 'Return a `record` with those fields, not a dictionary built with fixed string keys.',
            suggestion: 'Declare `public sealed record StockLine(string Sku, int Qty);` and return `new StockLine(sku, qty)`.',
        );
    }
}
