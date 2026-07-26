<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ClassLayout;

final class MemberAfterMethod extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'member-after-method',
            skill: ClassLayout::class,
            description: 'A trait use, constant, property, property hook or enum case declared BELOW a method — state a reader only meets after the behaviour that uses it',
            rule: "Declare what a class HAS above what it DOES: trait uses, constants, properties and hooks stand at the top, above the constructor — never between two methods or appended at the bottom.",
        );
    }
}
