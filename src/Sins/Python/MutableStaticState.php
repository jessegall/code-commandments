<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\FixAtTheSource;

final class MutableStaticState extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-mutable-static-state',
            skill: FixAtTheSource::class,
            description: 'a `global` written from a function, or a class attribute set from a method — state no instance owns, changed by whoever ran last',
            rule: 'Hold changing state on an instance someone owns and passes; never write a `global` or a class attribute from a function.',
            suggestion: 'Move the state onto an object, and hand that object to the code that reads and changes it.',
        );
    }
}
