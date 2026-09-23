<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\FixAtTheSource;

final class ConstructorSideEffect extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-constructor-side-effect',
            skill: FixAtTheSource::class,
            description: 'an `__init__` that tells a collaborator to act and throws the answer away — merely building the object changes the world',
            rule: 'Let `__init__` establish what the object IS; never let building one change anything outside it.',
            suggestion: 'Keep the collaborator as a field and act on it from the method that someone actually calls.',
        );
    }
}
