<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts;

use JesseGall\CodeCommandments\Vue\StringScan;
use JesseGall\CodeCommandments\Vue\Token;

/**
 * Tokenizes `<script setup>` source into lexemes for the Parser; emits punctuation one character
 * at a time and keeps byte spans. A CURSOR over one source, like the parsers it feeds — the
 * position and what has been read so far are its state, so no arm has to be handed them.
 */
final class Lexer
{
    private string $source = '';

    private int $length = 0;

    private int $pos = 0;

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
     * Read ONE thing, whatever the cursor is on. One arm per kind of lead, so a new token kind is
     * an arm and a method rather than another rung on a ladder that had grown to seven.
     */
    private function step(): void
    {
        $char = $this->source[$this->pos];

        match (true) {
            ctype_space($char) => $this->pos++,
            $this->opens('//') => $this->skipToEndOfLine(),
            $this->opens('/*') => $this->skipBlockComment(),
            self::quotes($char) => $this->takeString(),
            self::startsName($char) => $this->takeIdentifier(),
            ctype_digit($char) => $this->takeNumber(),
            default => $this->takePunctuation(),
        };
    }

    /**
     * Does the two-character sequence $lead begin at the cursor?
     */
    private function opens(string $lead): bool
    {
        return $this->source[$this->pos] === $lead[0] && ($this->source[$this->pos + 1] ?? '') === $lead[1];
    }

    private static function quotes(string $char): bool
    {
        return $char === '"' || $char === "'" || $char === '`';
    }

    /**
     * Can a NAME begin with this character? A JS identifier starts with a letter, `_` or `$`, and
     * continues with digits too ({@see continuesName}).
     */
    private static function startsName(string $char): bool
    {
        return ctype_alpha($char) || $char === '_' || $char === '$';
    }

    private static function continuesName(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '$';
    }

    /**
     * A `//` comment runs to the newline that ends it, or to the end of source.
     */
    private function skipToEndOfLine(): void
    {
        $newline = strpos($this->source, "\n", $this->pos);

        $this->pos = $newline === false ? $this->length : $newline;
    }

    /**
     * A `/* … *\/` comment, or an unterminated one, which runs to the end of source.
     */
    private function skipBlockComment(): void
    {
        $end = strpos($this->source, '*/', $this->pos);

        $this->pos = $end === false ? $this->length : $end + 2;
    }

    private function takeString(): void
    {
        $this->emit(Token::STRING, StringScan::skip($this->source, $this->pos, $this->source[$this->pos], $this->length));
    }

    private function takeIdentifier(): void
    {
        $end = $this->pos;

        while ($end < $this->length && self::continuesName($this->source[$end])) {
            $end++;
        }

        $this->emit(Token::IDENTIFIER, $end);
    }

    /**
     * A numeric literal, separators and all — `1_000` and `1.5` are ONE token, which is what stops
     * a separator lexing as an identifier.
     */
    private function takeNumber(): void
    {
        $end = $this->pos;

        while ($end < $this->length && (ctype_alnum($this->source[$end]) || $this->source[$end] === '.' || $this->source[$end] === '_')) {
            $end++;
        }

        $this->emit(Token::NUMBER, $end);
    }

    /**
     * Punctuation is emitted ONE character at a time; the parser composes `=>` and `?.` itself.
     */
    private function takePunctuation(): void
    {
        $this->emit(Token::PUNCTUATION, $this->pos + 1);
    }

    /**
     * Emit the run `[pos, $end)` as a $kind lexeme and advance the cursor past it — the one place a
     * token's text and span are cut from the source.
     */
    private function emit(string $kind, int $end): void
    {
        $this->tokens[] = new Lexeme($kind, substr($this->source, $this->pos, $end - $this->pos), $this->pos, $end);
        $this->pos = $end;
    }
}
