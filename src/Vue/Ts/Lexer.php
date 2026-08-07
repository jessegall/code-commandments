<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts;

use JesseGall\CodeCommandments\Vue\Lexeme;
use JesseGall\CodeCommandments\Vue\SourceLexer;
use JesseGall\CodeCommandments\Vue\Token;

/**
 * Tokenizes `<script setup>` source into {@see Lexeme}s for the {@see Parser}. What it adds to the
 * shared cursor is the two things a module has that a binding expression does not: comments, and
 * punctuation emitted ONE character at a time — the parser composes `=>` and `?.` itself.
 */
final class Lexer extends SourceLexer
{
    protected function takeOther(): void
    {
        match (true) {
            $this->opens('//') => $this->skipToEndOfLine(),
            $this->opens('/*') => $this->skipBlockComment(),
            default => $this->emit(Token::PUNCTUATION, $this->pos + 1),
        };
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
}
