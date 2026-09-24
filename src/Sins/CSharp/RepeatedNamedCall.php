<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\RepeatedCallHelper;

final class RepeatedNamedCall extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-repeated-named-call',
            skill: RepeatedCallHelper::class,
            description: 'the same `with` copy — `order with { Status = OrderStatus.Shipped }` — written at two or more sites, an operation the record never named',
            rule: 'Name a copy written the same way at several sites as a method on the record, and call it by that name.',
            suggestion: 'Add `public Order Shipped() => this with { Status = OrderStatus.Shipped };` to the record and write `order.Shipped()` at every site.',
        );
    }
}
