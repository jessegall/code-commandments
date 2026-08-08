<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Ts\Token;
use JesseGall\CodeCommandments\Ts\Lexeme;
use JesseGall\CodeCommandments\Ts\Parser;
use PHPUnit\Framework\TestCase;

final class LexemeTest extends TestCase
{
    public function test_the_none_lexeme_answers_every_question_with_no(): void
    {
        $none = Lexeme::none(12);

        // This is the whole point of the sentinel: a lookahead past the end asks its question
        // straight, and gets `false` — no `?->`, no `?? false` at the call site.
        $this->assertFalse($none->isPunct(','));
        $this->assertFalse($none->isPunct());
        $this->assertFalse($none->isIdentifier());
        $this->assertFalse($none->isIdentifier('function'));
        $this->assertFalse($none->is(Token::STRING));
        $this->assertFalse($none->isGroupOpener());
        $this->assertFalse($none->isGroupCloser());
    }

    public function test_only_the_none_lexeme_knows_it_is_absent(): void
    {
        $this->assertTrue(Lexeme::none(0)->isNone());
        $this->assertFalse(new Lexeme(Token::IDENTIFIER, 'x', 0, 1)->isNone());
    }

    public function test_its_span_points_at_the_offset_it_was_given(): void
    {
        // A span taken from it still lands somewhere real, so a node built at the end of the
        // source does not get a zero start.
        $none = Lexeme::none(41);

        $this->assertSame(41, $none->start);
        $this->assertSame(41, $none->end);
    }

    public function test_a_source_that_ends_mid_construct_still_parses_totally(): void
    {
        // The parser's contract is TOTAL — running off the end must not fatal, whatever it was
        // in the middle of when the tokens ran out.
        foreach (['const a =', 'import { X } from', 'function f(', 'type T = {', 'const x: Foo<'] as $truncated) {
            $module = Parser::module($truncated);

            $this->assertNotNull($module, "parsing did not survive: {$truncated}");
        }
    }
}
