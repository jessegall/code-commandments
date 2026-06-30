<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour;

final class EnumValueMatch extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'enum-value-match',
            skill: EnumsWithBehaviour::class,
            description: "`match`/`switch` over an enum's `->value` at a call site (homeless method)",
            rule: "Put per-case behaviour on the enum; never `match`/`switch` over its `->value` at a call site.",
            suggestion: "A method on the backed enum (`\$x->label()`, `\$x->isPaid()`)."
        );
    }
}
