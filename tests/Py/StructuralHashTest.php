<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A Python function's body fingerprint is blind to formatting, comments, docstrings and the name it is
 * declared under — a function and a method doing the same thing are the same code. The SHAPE fingerprint
 * also blanks local names and string/number literals, f-string text included, and keeps what is called
 * and which attributes are read.
 */
final class StructuralHashTest extends TestCase
{
    private const string TOTAL = <<<'PY'
        def total(items):
            total = 0
            for item in items:
                total += item.price * item.quantity
            return total
        PY;

    public function test_formatting_comments_and_a_docstring_do_not_change_the_body_hash(): void
    {
        $reformatted = <<<'PY'
            def total(items):
                """Sum every line."""
                total = 0  # running total
                for item in items: total += (item.price
                                             * item.quantity)
                return total
            PY;

        $this->assertSame($this->only(self::TOTAL)->bodyHash(), $this->only($reformatted)->bodyHash());
    }

    public function test_a_method_body_hashes_like_a_function_body_under_another_name(): void
    {
        $class = <<<'PY'
            class Cart:
                def sum_of(self, items):
                    total = 0
                    for item in items:
                        total += item.price * item.quantity
                    return total
            PY;

        $this->assertNotSame('', $this->only($class)->bodyHash());
        $this->assertSame($this->only(self::TOTAL)->bodyHash(), $this->only($class)->bodyHash());
    }

    public function test_a_renamed_local_changes_the_body_but_not_the_shape(): void
    {
        $renamed = str_replace(['total', 'item'], ['acc', 'line'], self::TOTAL);

        $this->assertNotSame($this->only(self::TOTAL)->bodyHash(), $this->only($renamed)->bodyHash());
        $this->assertSame($this->only(self::TOTAL)->shapeHash(), $this->only($renamed)->shapeHash());
    }

    public function test_a_different_literal_changes_the_body_but_not_the_shape(): void
    {
        $seeded = str_replace('total = 0', 'total = 10', self::TOTAL);

        $this->assertNotSame($this->only(self::TOTAL)->bodyHash(), $this->only($seeded)->bodyHash());
        $this->assertSame($this->only(self::TOTAL)->shapeHash(), $this->only($seeded)->shapeHash());
    }

    public function test_f_string_text_does_not_reach_the_shape_but_its_fields_do(): void
    {
        $orders = "def a(id):\n    return fetch(f\"/orders/{id}\")\n";

        $this->assertSame($this->only($orders)->shapeHash(), $this->only(str_replace('/orders/', '/customers/', $orders))->shapeHash());
        $this->assertNotSame($this->only($orders)->shapeHash(), $this->only(str_replace('{id}', '{id.key}', $orders))->shapeHash());
    }

    public function test_a_different_attribute_read_changes_the_shape(): void
    {
        $weighed = str_replace('item.quantity', 'item.weight', self::TOTAL);

        $this->assertNotSame($this->only(self::TOTAL)->shapeHash(), $this->only($weighed)->shapeHash());
    }

    public function test_a_different_function_called_changes_the_shape(): void
    {
        $price = "def a(x):\n    y = x * 2\n    return format_price(y, 'EUR')\n";

        $this->assertNotSame($this->only($price)->shapeHash(), $this->only(str_replace('format_price', 'format_date', $price))->shapeHash());
    }

    public function test_break_and_continue_are_different_code(): void
    {
        $break = "def a(rows):\n    for row in rows:\n        if row:\n            break\n    return rows\n";

        $this->assertNotSame($this->only($break)->shapeHash(), $this->only(str_replace('break', 'continue', $break))->shapeHash());
    }

    public function test_a_for_loop_and_a_while_loop_are_different_code(): void
    {
        $for = "def a(rows):\n    for row in rows:\n        handle(row)\n";
        $while = "def a(rows):\n    while rows:\n        handle(rows)\n";

        $this->assertNotSame($this->only($for)->shapeHash(), $this->only($while)->shapeHash());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function syncAndAsyncTwins(): iterable
    {
        yield 'for' => ["def a(s):\n    for p in s:\n        yield p\n", "async def a(s):\n    async for p in s:\n        yield p\n"];
        yield 'with' => ["def a(s):\n    with s:\n        return 1\n", "async def a(s):\n    async with s:\n        return 1\n"];
        yield 'nested def' => ["def a():\n    def b():\n        return 1\n    return b\n", "def a():\n    async def b():\n        return 1\n    return b\n"];
    }

    #[DataProvider('syncAndAsyncTwins')]
    public function test_an_async_statement_is_different_code_from_its_sync_twin(string $sync, string $async): void
    {
        $this->assertNotSame($this->only($sync)->bodyHash(), $this->only($async)->bodyHash());
    }

    public function test_the_body_weight_counts_statements_and_expressions_but_not_the_docstring(): void
    {
        $this->assertGreaterThan($this->only("def a():\n    return 1\n")->bodyNodeCount(), $this->only(self::TOTAL)->bodyNodeCount());
        $this->assertSame(
            $this->only("def a():\n    return 1\n")->bodyNodeCount(),
            $this->only("def a():\n    \"\"\"One.\"\"\"\n    return 1\n")->bodyNodeCount(),
        );
    }

    private function only(string $source): NodeMatch
    {
        return Codebase::fromString($source)->whereFunction()->get()[0];
    }
}
