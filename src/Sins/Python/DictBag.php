<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\ValueObjects;

final class DictBag extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-dict-bag',
            skill: ValueObjects::class,
            description: 'A parameter typed as a dict read by string keys — `row["sku"]`, `row.get("quantity")` — a record nobody declared',
            rule: 'Give a record a type — a frozen dataclass — instead of a dict read by string keys.',
            suggestion: 'Declare the keys as fields of a frozen dataclass, build it where the data enters (a `from_payload` classmethod), and take that type as the parameter.',
        );
    }
}
