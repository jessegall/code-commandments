<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;

final class DataClump extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'data-clump',
            skill: ValueObjects::class,
            description: "The same 3+ scalar params threaded through 2+ classes (a recurring data clump → one object)",
            rule: "Bundle values that always travel together into one object; don't thread 3+ of them as separate params.",
            suggestion: "A value object the params fold into (`Money::of()`, `NodePosition`)."
        );
    }
}
