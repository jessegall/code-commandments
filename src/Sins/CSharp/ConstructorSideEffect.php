<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\FixAtTheSource;

final class ConstructorSideEffect extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-constructor-side-effect',
            skill: FixAtTheSource::class,
            description: 'a constructor that calls a method on something it was handed and ignores the result — just creating the object changes something outside it',
            rule: 'A constructor sets up the object; creating one should never change anything outside it.',
            suggestion: 'Keep the collaborator in a field and call it from the method someone actually calls to do the work.',
        );
    }
}
