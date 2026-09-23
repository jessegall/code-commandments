<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Enums;

final class UnnamedVocabularyLiteral extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-unnamed-vocabulary-literal',
            skill: Enums::class,
            description: 'a raw string handed to a parameter the codebase elsewhere fills from a named constant — `expect("{")` beside `expect(Token.COLON)`, where `Token.BRACE_OPEN` already names it',
            rule: 'Where a parameter is spelled from a named vocabulary, spell it that way everywhere — never the raw value at one call and the constant at the next.',
            suggestion: 'The constant that already holds this value, referenced by name.',
        );
    }
}
