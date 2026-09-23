<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\FixAtTheSource;

final class MutableStaticState extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-mutable-static-state',
            skill: FixAtTheSource::class,
            description: 'a `static` field that methods write to — state no instance owns, changed by whichever code ran last',
            rule: 'Keep state that changes on an instance someone owns and passes around; don\'t write to a static field from a method.',
            suggestion: 'Move the field onto an object and hand that object to the code that reads and changes it; for a value computed once, use a `static readonly Lazy<T>`.',
        );
    }
}
