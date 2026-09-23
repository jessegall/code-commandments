<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Lexer;
use JesseGall\CodeCommandments\Py\Token;
use JesseGall\CodeCommandments\Py\TokenKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Python source cut into tokens the way the language reads it: a logical line ends in NEWLINE, a
 * deeper block opens with INDENT and closes with DEDENT, a line inside brackets or after a backslash
 * runs on, and a string keeps its prefix and its quotes whole.
 */
final class LexerTest extends TestCase
{
    public function test_blocks_open_and_close_with_indent_and_dedent(): void
    {
        $this->assertSame(
            ['def', 'f', '(', ')', ':', 'NEWLINE', 'INDENT', 'if', 'x', ':', 'NEWLINE', 'INDENT', 'return', '1', 'NEWLINE', 'DEDENT', 'return', '2', 'NEWLINE', 'DEDENT', 'ENDMARKER'],
            $this->shapes("def f():\n    if x:\n        return 1\n    return 2\n"),
        );
    }

    public function test_a_file_ending_mid_block_closes_every_block(): void
    {
        $this->assertSame(
            ['class', 'A', ':', 'NEWLINE', 'INDENT', 'def', 'f', '(', ')', ':', 'NEWLINE', 'INDENT', 'pass', 'NEWLINE', 'DEDENT', 'DEDENT', 'ENDMARKER'],
            $this->shapes("class A:\n    def f():\n        pass"),
        );
    }

    public function test_blank_and_comment_lines_do_not_end_or_open_anything(): void
    {
        $this->assertSame(
            ['if', 'x', ':', 'NEWLINE', 'INDENT', 'a', 'NEWLINE', 'b', 'NEWLINE', 'DEDENT', 'ENDMARKER'],
            $this->shapes("if x:\n    a\n\n  # a comment at another depth\n    b\n"),
        );
    }

    public function test_a_line_inside_brackets_runs_on(): void
    {
        $this->assertSame(
            ['x', '=', 'f', '(', '1', ',', '2', ',', ')', 'NEWLINE', 'ENDMARKER'],
            $this->shapes("x = f(\n    1,\n        2,\n)\n"),
        );
    }

    public function test_a_backslash_joins_the_next_line(): void
    {
        $this->assertSame(
            ['assert', 'a', '==', 'b', 'NEWLINE', 'ENDMARKER'],
            $this->shapes("assert a == \\\n    b\n"),
        );
    }

    public function test_an_inconsistent_dedent_still_closes_the_block(): void
    {
        $shapes = $this->shapes("if x:\n        a\n    b\n");

        $this->assertContains('DEDENT', $shapes);
        $this->assertSame('ENDMARKER', end($shapes));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function strings(): iterable
    {
        yield 'single' => ["'a'"];
        yield 'double' => ['"a"'];
        yield 'escaped quote' => ['"a\\"b"'];
        yield 'triple' => ["\"\"\"a\n'b'\n\"\"\""];
        yield 'raw' => ['r"\\d+"'];
        yield 'bytes' => ["b''"];
        yield 'raw bytes' => ['rb"\\x1b\\["'];
        yield 'f-string' => ['f"{x!r:>10}"'];
        yield 'f-string with the other quote inside' => ["f\"{d['k']}\""];
        yield 'f-string with its own quote inside' => ['f"{d["k"]}"'];
        yield 'nested f-string' => ["f\"{plural(n, f'unread {kind}')}\""];
        yield 'f-string with braces escaped' => ['f"{{literal}} {x}"'];
    }

    #[DataProvider('strings')]
    public function test_a_string_is_one_token_prefix_and_all(string $literal): void
    {
        $tokens = new Lexer()->tokenize("x = {$literal}\n");

        $this->assertSame(TokenKind::String, $tokens[2]->kind);
        $this->assertSame($literal, $tokens[2]->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function numbers(): iterable
    {
        foreach (['0', '42', '1_000', '3.14', '.5', '1e-3', '0xFF', '0o17', '0b1010', '2j', '1.5E+10'] as $number) {
            yield $number => [$number];
        }
    }

    #[DataProvider('numbers')]
    public function test_a_number_is_one_token(string $number): void
    {
        $tokens = new Lexer()->tokenize("x = {$number}\n");

        $this->assertSame(TokenKind::Number, $tokens[2]->kind);
        $this->assertSame($number, $tokens[2]->value);
    }

    public function test_operators_are_read_longest_first(): void
    {
        $this->assertSame(
            ['a', '**=', 'b', '//', 'c', 'NEWLINE', 'x', ':=', 'y', '->', 'z', '...', 'NEWLINE', 'ENDMARKER'],
            $this->shapes("a **= b // c\nx := y -> z ...\n"),
        );
    }

    public function test_every_token_knows_where_it_is(): void
    {
        $source = "def f():\n    return total\n";
        $tokens = new Lexer()->tokenize($source);
        $total = array_values(array_filter($tokens, static fn (Token $token): bool => $token->value === 'total'))[0];

        $this->assertSame('total', substr($source, $total->start, $total->end - $total->start));
    }

    public function test_comments_are_kept_beside_the_tokens(): void
    {
        $lexer = new Lexer();
        $lexer->tokenize("x = 1  # the first\n# @sin Something\ndef f(): pass\n");

        $this->assertSame(['# the first', '# @sin Something'], array_map(static fn (Token $comment): string => $comment->value, $lexer->comments()));
    }

    /**
     * @return list<string>
     */
    private function shapes(string $source): array
    {
        return array_map(
            static fn (Token $token): string => match ($token->kind) {
                TokenKind::Newline, TokenKind::Indent, TokenKind::Dedent, TokenKind::EndMarker => strtoupper($token->kind->value),
                default => $token->value,
            },
            new Lexer()->tokenize($source),
        );
    }
}
