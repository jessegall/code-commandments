<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts;

use JesseGall\CodeCommandments\Vue\Token;

/**
 * One token from the {@see Lexer} — a kind ({@see Token} constant), its source text, and the byte
 * span it covers (so the parser can tell a newline-terminated member from a continued one, and a
 * node can point back at its source). A value object, not an array bag: the parser reads
 * `$lexeme->value` / `$lexeme->is(...)`, never a string-keyed lookup.
 */
final readonly class Lexeme
{
    public function __construct(
        public string $kind,
        public string $value,
        public int $start,
        public int $end,
    ) {}

    public function is(string $kind, ?string $value = null): bool
    {
        return $this->kind === $kind && ($value === null || $this->value === $value);
    }

    public function isIdentifier(?string $value = null): bool
    {
        return $this->is(Token::IDENTIFIER, $value);
    }

    public function isPunct(?string $value = null): bool
    {
        return $this->is(Token::PUNCTUATION, $value);
    }
}
