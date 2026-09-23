<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\ClassLayout;

final class MemberAfterMethod extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-member-after-method',
            skill: ClassLayout::class,
            description: 'a constant, class attribute or field declared below a method — the class\'s state hidden among its behaviour',
            rule: 'Declare a class\'s state at the top — constants, class attributes and fields above `__init__` and every method.',
            suggestion: 'Move the assignment up to the head of the class, with the other state.',
        );
    }
}
