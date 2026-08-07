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

    /**
     * The NULL lexeme — what a parser gets when it looks past the last token. It answers every
     * `is…` question with `false` (its kind is one no lexer emits), so a lookahead reads
     * `$this->at(1)->isPunct(',')` instead of `($this->at(1)?->isPunct(',') ?? false)`. Its span
     * sits at $at, the end of the source, so a span taken from it still points somewhere real.
     */
    public static function none(int $at): self
    {
        return new self(Token::NONE, '', $at, $at);
    }

    /**
     * Is this the past-the-end lexeme — the one question that IS about absence, for the few
     * places that must stop rather than simply not match.
     */
    public function isNone(): bool
    {
        return $this->kind === Token::NONE;
    }

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

    /**
     * Is this lexeme the OPENING bracket of a group? A depth counter asks it of every token, and asking
     * the token beats testing its kind and then handing its text to {@see Token}.
     */
    public function isGroupOpener(): bool
    {
        return $this->isPunct() && Token::opensGroup($this->value);
    }

    /**
     * Is this lexeme the OPENING bracket of a TYPE — the `<` of `Ref<T>` and its kin?
     */
    public function isTypeOpener(): bool
    {
        return $this->isPunct() && Token::opensType($this->value);
    }

    /**
     * Is this lexeme the CLOSING bracket of a type?
     */
    public function isTypeCloser(): bool
    {
        return $this->isPunct() && Token::closesType($this->value);
    }

    /**
     * Is this lexeme the CLOSING bracket of a group?
     */
    public function isGroupCloser(): bool
    {
        return $this->isPunct() && Token::closesGroup($this->value);
    }
}
