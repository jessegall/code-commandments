<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow;

final class NestedTernary extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'nested-ternary',
            skill: GuardClausesAndFlow::class,
            description: "Nested/chained ternary `\$a ? \$b : (\$c ? \$d : \$e)` (hidden control flow)"
        );
    }
}
