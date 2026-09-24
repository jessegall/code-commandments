<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\ValueObjects;

final class CoupledFields extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-coupled-fields',
            skill: ValueObjects::class,
            description: 'a type whose own fields always travel together — assembled into one value again and again, null-checked together, or one copying what a sibling field already holds — one concept held as several fields',
            rule: 'Fields that move as a unit are one type: hold the value object, not its parts; never keep a second copy of what a sibling field already holds.',
            suggestion: 'Fold the fields into one record (reuse one that already matches, if one exists) and drop a field that only mirrors a sibling\'s member.',
        );
    }
}
