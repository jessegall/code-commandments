<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

use JesseGall\CodeCommandments\Ts\SourceLexer;
use JesseGall\CodeCommandments\Ts\Token;

/**
 * Tokenizes TypeScript source into {@see \JesseGall\CodeCommandments\Ts\Lexeme}s for the
 * {@see Parser}. What it adds to the shared cursor is the two things a module has that a binding
 * expression does not: comments, which it CONSUMES so no grammar rule reads "unless it's a comment",
 * and punctuation emitted ONE character at a time — the parser composes `=>` and `?.` itself.
 */
final class Lexer extends SourceLexer
{
    protected function takeOther(): void
    {
        match (true) {
            $this->opens('//') => $this->skipLineComment(),
            $this->opens('/*') => $this->skipBlockComment(),
            default => $this->emit(Token::PUNCTUATION, $this->pos + 1),
        };
    }

    /**
     * A `//` comment — everything up TO the newline that ends it (the newline itself is left for the
     * cursor, so statement-terminating line breaks survive), or the end of source.
     */
    private function skipLineComment(): void
    {
        while ($this->pos < $this->length && $this->source[$this->pos] !== "\n") {
            $this->pos++;
        }
    }

    /**
     * A `/* … *\/` comment, closing delimiter included — or, unterminated, everything that is left.
     */
    private function skipBlockComment(): void
    {
        $this->pos += 2; // the `/*`, so `/*/` does not read as opener and closer at once

        while ($this->pos < $this->length) {
            if ($this->opens('*/')) {
                $this->pos += 2;

                return;
            }

            $this->pos++;
        }
    }
}
