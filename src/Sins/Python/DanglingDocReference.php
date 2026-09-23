<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Documentation;

final class DanglingDocReference extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-dangling-doc-reference',
            skill: Documentation::class,
            description: 'a Sphinx cross-reference in a docstring (`:class:`shop.cart.Basket``) to a first-party name the codebase no longer declares',
            rule: 'A cross-reference must resolve: point it at the name that exists now, or delete it.',
            suggestion: 'Repoint the reference at the current module or class, or remove it.',
        );
    }
}
