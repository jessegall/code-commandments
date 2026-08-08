<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour;

final class UnnamedVocabularyLiteral extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'unnamed-vocabulary-literal',
            skill: EnumsWithBehaviour::class,
            description: "A raw string in an argument the codebase elsewhere fills from a named vocabulary — `expect('{')` beside `expect(Token::COLON)`, where `Token::BRACE_OPEN` already names it",
            rule: "Where a parameter is spelled from a named vocabulary, spell it that way EVERYWHERE — never the raw value at one call site and the constant at the next.",
            suggestion: "The constant that already holds this value, referenced by name.",
        );
    }
}
