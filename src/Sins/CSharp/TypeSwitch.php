<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\TellDontAsk;

final class TypeSwitch extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-type-switch',
            skill: TellDontAsk::class,
            description: '`shape switch { Circle c => …, Square s => … }` — asking which of your own types a value is, to decide what to do with it',
            rule: 'Give the base type a member each type answers, and call it; don\'t switch on which type a value is.',
            suggestion: 'Declare `public abstract double Area();` on `Shape`, implement it on `Circle` and `Square`, and write `shape.Area()`.',
        );
    }
}
