<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * A CURSOR over one JS/TS source, cutting it into {@see Lexeme}s. Whitespace, strings, names and
 * numbers are lexed the same way in every dialect the Vue engine reads, so they are stated here
 * once; what a dialect does with everything ELSE is its own — {@see takeOther} is the single hook,
 * and it is the entire difference between the two lexers that extend this.
 *
 * @see Ts\Lexer   `<script setup>` — comments, and punctuation one character at a time
 * @see Expr\Lexer a binding expression — no comments, and multi-character operators emitted whole
 */
abstract class SourceLexer
{
    protected string $source = '';

    protected int $length = 0;

    protected int $pos = 0;

    /**
     * @var list<Lexeme>
     */
    private array $tokens = [];

    /**
     * @return list<Lexeme>
     */
    public function tokenize(string $source): array
    {
        $this->source = $source;
        $this->length = strlen($source);
        $this->pos = 0;
        $this->tokens = [];

        while ($this->pos < $this->length) {
            $this->step();
        }

        return $this->tokens;
    }

    /**
     * Whatever the cursor is on that is not whitespace, a string, a name or a number — punctuation,
     * and whatever else the dialect writes there. It must advance the cursor, or the scan spins.
     */
    abstract protected function takeOther(): void;

    /**
     * Emit the run `[pos, $end)` as a $kind lexeme and advance past it — the one place a token's
     * text and span are cut from the source.
     */
    protected function emit(string $kind, int $end): void
    {
        $this->tokens[] = new Lexeme($kind, substr($this->source, $this->pos, $end - $this->pos), $this->pos, $end);
        $this->pos = $end;
    }

    /**
     * Does the two-character sequence $lead begin at the cursor?
     */
    protected function opens(string $lead): bool
    {
        return $this->source[$this->pos] === $lead[0] && ($this->source[$this->pos + 1] ?? '') === $lead[1];
    }

    /**
     * Read ONE thing, whatever the cursor is on. One arm per kind of lead, so a new token kind is an
     * arm and a method rather than another rung on a ladder.
     */
    private function step(): void
    {
        $char = $this->source[$this->pos];

        match (true) {
            ctype_space($char) => $this->pos++,
            Token::quotes($char) => $this->takeString(),
            Token::startsName($char) => $this->takeIdentifier(),
            ctype_digit($char) => $this->takeNumber(),
            default => $this->takeOther(),
        };
    }

    private function takeString(): void
    {
        $this->emit(Token::STRING, StringScan::skip($this->source, $this->pos, $this->source[$this->pos], $this->length));
    }

    private function takeIdentifier(): void
    {
        $end = $this->pos;

        while ($end < $this->length && Token::continuesName($this->source[$end])) {
            $end++;
        }

        $this->emit(Token::IDENTIFIER, $end);
    }

    /**
     * A numeric literal, separators and all — `1_000` is ONE token, which is what stops a separator
     * lexing as an identifier.
     */
    private function takeNumber(): void
    {
        $end = $this->pos;

        while ($end < $this->length && Token::continuesNumber($this->source[$end])) {
            $end++;
        }

        $this->emit(Token::NUMBER, $end);
    }
}
