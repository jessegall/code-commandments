<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\ClassLayout;

final class MemberOutOfOrder extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-member-out-of-order',
            skill: ClassLayout::class,
            description: 'a `const` or `static readonly` value declared below a field or a stored property — the top of the type read in no particular order',
            rule: 'Declare constants first, then fields, then stored properties.',
            suggestion: 'Move the constant up above the fields.',
        );
    }
}
