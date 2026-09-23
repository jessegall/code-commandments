<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Documentation;

final class CeremonyDocblock extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-ceremony-docblock',
            skill: Documentation::class,
            description: 'a docstring with no summary whose every entry restates the annotated signature — `order (Order):`, `:rtype: int`',
            rule: 'A docstring must add meaning beyond the signature; drop entries that only repeat an annotation.',
            suggestion: 'Delete the docstring, or write the sentence that says what the function does and describe only what a type cannot.',
        );
    }
}
