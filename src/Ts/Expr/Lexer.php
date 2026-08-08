<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Expr;

use JesseGall\CodeCommandments\Ts\Lexeme;
use JesseGall\CodeCommandments\Ts\SourceLexer;
use JesseGall\CodeCommandments\Ts\Token;

/**
 * Tokenizes a Vue binding expression — the JS in `:x="…"` / `v-if="…"` / `{{ … }}` — into the same
 * {@see Lexeme}s the TS {@see \JesseGall\CodeCommandments\Ts\Lexer} emits. All it adds to the
 * shared cursor is punctuation: an expression's operators are MULTI-character (`===`, `??`, `?.`,
 * `=>`), so they are matched maximal-munch and emitted whole.
 */
final class Lexer extends SourceLexer
{
    /**
     * Every punctuation token, ordered so an operator is matched before any operator that is a
     * PREFIX of it — the maximal-munch rule, so `===` is one token rather than `==` then `=`.
     */
    private const array PUNCTUATION = [
        '?.', '===', '!==', '==', '!=', '<=', '>=', '&&', '||', '??', '=>',
        '.', '(', ')', '[', ']', '{', '}', ',', '?', ':', '!', '<', '>', '+', '-', '*', '/', '%', '=',
    ];

    /**
     * The longest operator that begins at the cursor, emitted whole. A byte that begins none of them
     * is unknown syntax and is skipped, so the scan always makes progress.
     */
    protected function takeOther(): void
    {
        foreach (self::PUNCTUATION as $punct) {
            if (substr($this->source, $this->pos, strlen($punct)) !== $punct) {
                continue;
            }

            $this->emit(Token::PUNCTUATION, $this->pos + strlen($punct));

            return;
        }

        $this->pos++;
    }
}
