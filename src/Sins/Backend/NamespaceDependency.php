<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\DependencyDirection;

final class NamespaceDependency extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'namespace-dependency',
            skill: DependencyDirection::class,
            description: "a declared layer references a layer it may not use (the arrow points back up)",
            rule: "Reference only DOWN the declared stack — a layer may use the layers it declared in `mayUse`, and nothing else that is declared.",
            suggestion: "invert the arrow: take the value/contract the low layer needs, and let the high layer supply it",
        );
    }
}
