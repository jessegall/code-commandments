<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;

final class ConstructorSideEffect extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'constructor-side-effect',
            skill: FixAtTheSource::class,
            description: 'A constructor that performs a side effect on a collaborator and throws away the result, so simply creating the object changes something outside it.',
            rule: "Let a constructor establish what the object IS; never let building one change anything outside it.",
            suggestion: "Keep the collaborator as a field and act on it from the method that someone actually calls.",
        );
    }
}
