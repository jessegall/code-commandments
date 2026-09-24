<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\TypeHonesty;

final class PlaceholderFilledData extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-placeholder-filled-data',
            skill: TypeHonesty::class,
            description: '`new Card(title, "")` — a record\'s required `string` filled with a blank so the record can be built, hiding a missing value no type check can see',
            rule: 'A required slot means the caller has the value; fill it with the real value, never with `""`.',
            suggestion: 'Fetch the real value, or make the slot `string?` if it really can be missing — or split off a smaller record that only promises what you have.',
        );
    }
}
