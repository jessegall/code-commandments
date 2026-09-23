<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Enums;

final class StringMirrorsEnum extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-string-mirrors-enum',
            skill: Enums::class,
            description: 'A `switch` or an `if` ladder dispatching on strings that are the names of an enum the codebase already declares — the enum, written out again as text',
            rule: 'Dispatch on the enum, never on strings that spell its members — parse the string into the enum where it enters.',
            suggestion: 'Parse the string into the enum at the edge (`Enum.Parse<T>` or the JSON converter), switch on the enum, and put the per-case answer beside it.',
        );
    }
}
