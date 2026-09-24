<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\ClassLayout;

final class MemberAfterMethod extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-member-after-method',
            skill: ClassLayout::class,
            description: 'a field, constant or stored property declared below a constructor or a method — the type\'s state hidden among its behaviour',
            rule: 'Declare constants, fields and stored properties above the constructor, before any method.',
            suggestion: 'Move the declaration up to the other state at the top of the type.',
        );
    }
}
