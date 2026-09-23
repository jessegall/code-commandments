<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ts;

use JesseGall\CodeCommandments\Ts\ModuleFile;
use JesseGall\CodeCommandments\Ts\NodeMatch;
use PHPUnit\Framework\TestCase;

/**
 * A function's body fingerprint is blind to formatting, comments and the name it is declared under —
 * a `function`, a method and a `const` arrow doing the same thing are the same code. The SHAPE
 * fingerprint also blanks local names and string/number literals, and keeps what is called and read.
 */
final class StructuralHashTest extends TestCase
{
    private const string TOTAL = <<<'TS'
        function total(items: Item[]): number {
            let sum = 0;
            for (const item of items) {
                sum += item.price * item.quantity;
            }
            return sum;
        }
        TS;

    public function test_formatting_and_comments_do_not_change_the_body_hash(): void
    {
        $reformatted = <<<'TS'
            function total(items: Item[]): number {
                // running total
                let sum = 0;
                for (const item of items) { sum += item.price * item.quantity; }
                return sum;
            }
            TS;

        $this->assertSame($this->only(self::TOTAL)->bodyHash(), $this->only($reformatted)->bodyHash());
    }

    public function test_a_const_arrow_and_a_function_with_one_body_share_it_under_different_names(): void
    {
        $arrow = <<<'TS'
            const sumOf = (items: Line[]) => {
                let sum = 0;
                for (const item of items) {
                    sum += item.price * item.quantity;
                }
                return sum;
            };
            TS;

        $this->assertNotSame('', $this->only($arrow)->bodyHash());
        $this->assertSame($this->only(self::TOTAL)->bodyHash(), $this->only($arrow)->bodyHash());
    }

    public function test_a_method_body_hashes_like_a_function_body(): void
    {
        $class = <<<'TS'
            class Basket {
                total(items: Item[]): number {
                    let sum = 0;
                    for (const item of items) {
                        sum += item.price * item.quantity;
                    }
                    return sum;
                }
            }
            TS;

        $this->assertSame($this->only(self::TOTAL)->bodyHash(), $this->only($class)->bodyHash());
    }

    public function test_a_renamed_local_changes_the_body_but_not_the_shape(): void
    {
        $renamed = str_replace('sum', 'acc', self::TOTAL);

        $this->assertNotSame($this->only(self::TOTAL)->bodyHash(), $this->only($renamed)->bodyHash());
        $this->assertSame($this->only(self::TOTAL)->shapeHash(), $this->only($renamed)->shapeHash());
    }

    public function test_a_different_literal_changes_the_body_but_not_the_shape(): void
    {
        $seeded = str_replace('let sum = 0;', 'let sum = 10;', self::TOTAL);

        $this->assertNotSame($this->only(self::TOTAL)->bodyHash(), $this->only($seeded)->bodyHash());
        $this->assertSame($this->only(self::TOTAL)->shapeHash(), $this->only($seeded)->shapeHash());
    }

    public function test_a_different_member_read_changes_the_shape(): void
    {
        $weighed = str_replace('item.price', 'item.weight', self::TOTAL);

        $this->assertNotSame($this->only(self::TOTAL)->shapeHash(), $this->only($weighed)->shapeHash());
    }

    public function test_a_different_function_called_changes_the_shape(): void
    {
        $formatPrice = 'function a(x: number) { const y = x * 2; return formatPrice(y, "EUR"); }';
        $formatDate = 'function b(x: number) { const y = x * 2; return formatDate(y, "EUR"); }';

        $this->assertNotSame($this->only($formatPrice)->shapeHash(), $this->only($formatDate)->shapeHash());
    }

    public function test_a_literal_in_a_call_initialiser_does_not_reach_the_shape(): void
    {
        $orders = 'function a(id: number) { const response = fetch(`/orders/${id}`); return response.then((r) => r.json()); }';

        $this->assertSame($this->only($orders)->shapeHash(), $this->only(str_replace('/orders/', '/customers/', $orders))->shapeHash());
    }

    public function test_break_and_continue_are_different_code(): void
    {
        $break = 'function a(xs: X[]) { for (const x of xs) { if (x.done) { break; } run(x); } }';

        $this->assertNotSame($this->only($break)->bodyHash(), $this->only(str_replace('break', 'continue', $break))->bodyHash());
        $this->assertNotSame($this->only($break)->shapeHash(), $this->only(str_replace('break', 'continue', $break))->shapeHash());
    }

    public function test_for_of_and_for_in_are_different_code(): void
    {
        $of = 'function a(xs: X[]) { for (const x of xs) { run(x); } }';

        $this->assertNotSame($this->only($of)->bodyHash(), $this->only(str_replace(' of ', ' in ', $of))->bodyHash());
    }

    public function test_bodies_that_differ_only_inside_a_labelled_loop_are_different_code(): void
    {
        $labelled = 'function a(rows: R[][]) { outer: for (const row of rows) { for (const cell of row) { if (cell.stop) { break outer; } first(cell); } } }';

        $this->assertNotSame($this->only($labelled)->bodyHash(), $this->only(str_replace('first(cell)', 'second(cell)', $labelled))->bodyHash());
    }

    public function test_the_body_weight_counts_statements_and_expressions(): void
    {
        $this->assertGreaterThan($this->only('function a() { return 1; }')->bodyNodeCount(), $this->only(self::TOTAL)->bodyNodeCount());
    }

    private function only(string $source): NodeMatch
    {
        $module = ModuleFile::fromFile($source, 'a.ts');

        foreach ($module->nodes() as $node) {
            $match = new NodeMatch($node, $module);

            if ($match->bodyHash() !== '') {
                return $match;
            }
        }

        $this->fail('no function body in the source');
    }
}
