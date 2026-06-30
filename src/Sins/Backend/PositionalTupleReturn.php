<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;

final class PositionalTupleReturn extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'positional-tuple-return',
            skill: ValueObjects::class,
            description: "Returning a positional TUPLE — `return [\$node, \$key, \$inputs, \$outputs]` — bundling independent values as a keyless list the caller destructures by position",
            rule: "Return a typed object, not a positional tuple `[\$a, \$b, \$c]` the caller destructures by position.",
            suggestion: "A small `readonly` result object."
        );
    }
}
