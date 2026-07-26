<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ClassLayout;

final class MemberOutOfOrder extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'member-out-of-order',
            skill: ClassLayout::class,
            description: 'A declaration in the head of a class that arrives after something belonging below it — a constant under a property, a public field under a private one, a hook above the fields it reads',
            rule: "Order the head of a class the same way every time: trait uses, enum cases, constants, static properties, then instance properties public → protected → private, and hooked (derived) properties last, after the fields they read from.",
        );
    }
}
