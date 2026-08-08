<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ts\Expr;

use JesseGall\CodeCommandments\Ts\Expr\Lexer;
use JesseGall\CodeCommandments\Ts\Lexeme;
use JesseGall\CodeCommandments\Ts\Token;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    public function test_an_operator_is_emitted_whole(): void
    {
        // The one thing this lexer does that the TS one does not: `===` is ONE token, not `==`
        // followed by `=`, so the Pratt parser can read its precedence table straight off it.
        $this->assertSame(
            [[Token::IDENTIFIER, 'a'], [Token::PUNCTUATION, '==='], [Token::IDENTIFIER, 'b']],
            $this->lex('a === b'),
        );

        $this->assertSame(
            [[Token::IDENTIFIER, 'user'], [Token::PUNCTUATION, '?.'], [Token::IDENTIFIER, 'name']],
            $this->lex('user?.name'),
        );

        $this->assertSame(
            [[Token::IDENTIFIER, 'x'], [Token::PUNCTUATION, '=>'], [Token::IDENTIFIER, 'x']],
            $this->lex('x => x'),
        );
    }

    public function test_a_numeric_separator_is_not_read_as_an_identifier(): void
    {
        // `1_000` is one number. Scanning digits only would end the token at the `_` and lex the
        // rest as an identifier, so a template's `total > 1_000` would compare against 1.
        $this->assertSame([[Token::NUMBER, '1_000']], $this->lex('1_000'));
        $this->assertSame([[Token::NUMBER, '1.5']], $this->lex('1.5'));
    }

    public function test_a_string_keeps_its_quotes_and_escapes(): void
    {
        $this->assertSame([[Token::STRING, "'a\\'b'"]], $this->lex("'a\\'b'"));
        $this->assertSame([[Token::STRING, '`hi`']], $this->lex('`hi`'));
    }

    public function test_a_byte_that_begins_no_operator_is_skipped(): void
    {
        // `#` is not punctuation this grammar knows. The scan must still advance past it, or
        // tokenizing a stray character spins forever.
        $this->assertSame([[Token::IDENTIFIER, 'a'], [Token::IDENTIFIER, 'b']], $this->lex('a # b'));
    }

    /**
     * @return list<array{string, string}>
     */
    private function lex(string $source): array
    {
        return array_map(
            static fn (Lexeme $lexeme): array => [$lexeme->kind, $lexeme->value],
            new Lexer()->tokenize($source),
        );
    }
}
