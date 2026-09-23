<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\ValueObjects;

final class CoupledFields extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-coupled-fields',
            skill: ValueObjects::class,
            description: 'a class whose own fields always travel together — assembled into one value again and again, guarded together, or one mirroring a sibling\'s — one concept held as several fields',
            rule: 'Fields that move as a unit are one type: hold the value object, not its parts; never keep a second copy of what a sibling field already holds.',
            suggestion: 'Fold the fields into one frozen dataclass (name the existing one when the clump already is it) and drop a field that mirrors a sibling\'s attribute.',
        );
    }
}
