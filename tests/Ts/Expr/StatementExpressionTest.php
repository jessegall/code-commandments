<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ts\Expr;

use JesseGall\CodeCommandments\Ts\Expr\ExprKind;
use JesseGall\CodeCommandments\Ts\Expr\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The expressions a function BODY is made of, beyond what a template binding holds — a compound
 * assignment, `await`, `new`, `++`, a spread, `instanceof`, a non-null `!`. Each used to stop the
 * parse at its first token, keeping a fragment that told two different statements apart from nothing.
 */
final class StatementExpressionTest extends TestCase
{
    public function test_a_compound_assignment_keeps_its_operator_and_value(): void
    {
        $assign = Parser::parse('sum += item.price * item.quantity');

        $this->assertSame(ExprKind::Assign, $assign->kind);
        $this->assertSame('+=', $assign->get('op'));
        $this->assertSame(ExprKind::Binary, $assign->get('value')->kind);
    }

    public function test_a_plain_assignment_says_its_operator(): void
    {
        $this->assertSame('=', Parser::parse('open.value = false')->get('op'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function prefixes(): iterable
    {
        yield 'await' => ['await load(page)', 'await'];
        yield 'new' => ['new Map(entries)', 'new'];
        yield 'void' => ['void promise', 'void'];
        yield 'delete' => ['delete cache[key]', 'delete'];
        yield 'increment' => ['++count', '++'];
        yield 'spread' => ['...rest', '...'];
    }

    #[DataProvider('prefixes')]
    public function test_a_prefix_operator_wraps_the_whole_operand(string $source, string $operator): void
    {
        $unary = Parser::parse($source);

        $this->assertSame(ExprKind::Unary, $unary->kind);
        $this->assertSame($operator, $unary->get('op'));
        $this->assertNotSame(ExprKind::Unknown, $unary->get('argument')->kind);
    }

    public function test_await_reaches_the_call_it_waits_on(): void
    {
        $this->assertSame(ExprKind::Call, Parser::parse('await api.get(url)')->get('argument')->kind);
    }

    public function test_a_postfix_update_wraps_its_target(): void
    {
        $update = Parser::parse('count++');

        $this->assertSame(ExprKind::Unary, $update->kind);
        $this->assertSame('++', $update->get('op'));
        $this->assertSame('count', $update->get('argument')->get('name'));
    }

    public function test_instanceof_is_a_binary_operator(): void
    {
        $test = Parser::parse('error instanceof HttpError');

        $this->assertSame(ExprKind::Binary, $test->kind);
        $this->assertSame('instanceof', $test->get('op'));
    }

    public function test_a_non_null_assertion_is_transparent(): void
    {
        $call = Parser::parse('form.value!.reset()');

        $this->assertSame(ExprKind::Call, $call->kind);
        $this->assertSame('reset', $call->get('callee')->get('property'));
    }

    public function test_a_spread_argument_is_one_argument(): void
    {
        $call = Parser::parse('merge(...parts, extra)');

        $this->assertCount(2, $call->get('arguments'));
        $this->assertSame('...', $call->get('arguments')[0]->get('op'));
    }
}
