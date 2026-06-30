<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Documentation;

final class CeremonyDocblock extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'ceremony-docblock',
            skill: Documentation::class,
            description: "Docblock that only restates the typed signature (`@param Type \$x`, no description)"
        );
    }
}
