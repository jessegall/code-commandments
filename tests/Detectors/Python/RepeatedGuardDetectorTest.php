<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\RepeatedGuardDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepeatedGuardDetectorTest extends TestCase
{
    /**
     * @param  list<int>  $lines
     */
    #[DataProvider('recurring')]
    public function test_flags_every_copy_of_one_compound_condition(string $source, array $lines): void
    {
        $this->assertSame($lines, $this->linesIn($source));
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function recurring(): iterable
    {
        yield 'written in two functions' => ["def ship(order):\n    if order.paid and not order.cancelled:\n        send(order)\n\n\ndef invoice(order):\n    return order.paid and not order.cancelled\n", [2, 7]];
        yield 'reordered and read through a local' => ["def ship(order):\n    if order.paid and order.lines:\n        send(order)\n\n\ndef invoice(order):\n    paid = order.paid\n    if order.lines and paid:\n        bill(order)\n", [2, 8]];
        yield 'the outermost and of a longer chain' => ["def a(o):\n    return o.paid and o.lines and o.owner\n\n\ndef b(o):\n    return o.owner and o.paid and o.lines\n", [2, 6]];
    }

    public function test_leaves_a_condition_written_once(): void
    {
        $this->assertSame([], $this->linesIn("def ship(order):\n    if order.paid and not order.cancelled:\n        send(order)\n"));
    }

    public function test_leaves_trivial_conditions_with_no_reach(): void
    {
        $this->assertSame([], $this->linesIn("def a(x, y):\n    return x and y\n\n\ndef b(x, y):\n    return x and y\n"));
    }

    public function test_leaves_a_pure_type_check_to_the_type_guard_rule(): void
    {
        $source = "def a(node):\n    return isinstance(node, Call) and isinstance(node.func, Attribute)\n\n\ndef b(node):\n    return isinstance(node, Call) and isinstance(node.func, Attribute)\n";

        $this->assertSame([], $this->linesIn($source));
    }

    public function test_leaves_a_value_reached_through_and(): void
    {
        $this->assertSame([], $this->linesIn("def a(info):\n    obj = info and info.ref.load()\n    return obj\n\n\ndef b(info):\n    obj = info and info.ref.load()\n    return obj\n"));
    }

    public function test_leaves_different_conditions(): void
    {
        $this->assertSame([], $this->linesIn("def a(o):\n    return o.paid and o.lines\n\n\ndef b(o):\n    return o.paid and o.owner\n"));
    }

    /**
     * @return list<int>
     */
    private function linesIn(string $source): array
    {
        return array_map(static fn (ExprMatch $match): int => $match->line(), new RepeatedGuardDetector()->find(Codebase::fromString($source)));
    }
}
