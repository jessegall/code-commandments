<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Enums;

final class ConstClassEnum extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-const-class-enum',
            skill: Enums::class,
            description: 'a class that holds nothing but `const` strings or numbers — a closed set of values written as constants instead of an `enum`',
            rule: 'Make a closed set of values an `enum`, not a class of `const` strings or numbers.',
            suggestion: 'Declare `public enum Status { Pending, Paid }`, and serialise it by name with `JsonStringEnumConverter` where it must still read as its string.',
        );
    }
}
