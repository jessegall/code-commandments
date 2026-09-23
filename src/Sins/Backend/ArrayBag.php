<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;

final class ArrayBag extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'array-bag',
            skill: ValueObjects::class,
            description: 'String-indexing (`$arr[\'key\']`) a structured array param instead of giving it a name — the type was never defined.',
            rule: "Give a structured array a typed value object — never read a named field by string key off an `array` param.",
            suggestion: "A Spatie `Data` object built via `::from(\$array)`."
        );
    }
}
