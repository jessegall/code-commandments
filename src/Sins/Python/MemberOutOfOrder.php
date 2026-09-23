<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\ClassLayout;

final class MemberOutOfOrder extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-member-out-of-order',
            skill: ClassLayout::class,
            description: 'a constant declared below a field in the head of a class — the inventory read in an ad-hoc order',
            rule: 'Read a class\'s head in one fixed order: constants (`UPPER_CASE`, `Final`, `ClassVar`) first, then fields.',
            suggestion: 'Move the constant above the first field.',
        );
    }
}
