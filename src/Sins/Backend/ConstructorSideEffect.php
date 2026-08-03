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
            description: "a constructor that performs a SIDE EFFECT on a collaborator — the result thrown away, so merely building the object changes the world",
            rule: "Let a constructor establish what the object IS; never let building one change anything outside it.",
            suggestion: "Keep the collaborator as a field and act on it from the method that someone actually calls.",
        );
    }
}
