<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Enums;

final class UnnamedVocabularyLiteral extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-unnamed-vocabulary-literal',
            skill: Enums::class,
            description: 'a raw string handed to a parameter the codebase elsewhere fills from a named constant — `Expect("{")` beside `Expect(Token.Colon)`, where `Token.BraceOpen` already names it',
            rule: 'Where a parameter is spelled from a named vocabulary, spell it that way everywhere — never the raw value at one call and the constant at the next.',
            suggestion: 'Replace the literal with the constant that names it (`Token.BraceOpen`); better still, make the vocabulary an enum and the parameter take it.',
        );
    }
}
