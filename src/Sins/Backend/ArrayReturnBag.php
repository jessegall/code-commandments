<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;

final class ArrayReturnBag extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'array-return-bag',
            skill: ValueObjects::class,
            description: "Returning a multi-field string-keyed array literal (a bag that should be a value object)",
            rule: "Return a typed value object, not a multi-field string-keyed array literal.",
            suggestion: "Return a Spatie `Data` object via `::from(...)`."
        );
    }
}
