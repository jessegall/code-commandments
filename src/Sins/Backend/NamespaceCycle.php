<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\DependencyDirection;

final class NamespaceCycle extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'namespace-cycle',
            skill: DependencyDirection::class,
            description: "two namespaces that reference each other — neither can be read, tested or moved alone",
            rule: "Break every namespace cycle — dependencies point ONE way, so a namespace can always be lifted out on its own.",
            suggestion: "cut the weaker arrow: move the shared class down, or have the lower namespace own an interface the higher one implements",
        );
    }
}
