<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use PHPUnit\Framework\TestCase;

final class ArgumentFlowTest extends TestCase
{
    private const string SHOP = <<<'PY'
        class Cart:
            def add(self, sku, quantity=1, *, gift=False):
                pass

            @staticmethod
            def parse(text, strict):
                pass


        def ship(order, carrier, *rest):
            pass


        def run(cart: Cart, order, extra):
            ship(order, "post")
            ship(order, carrier="dhl")
            cart.add("A1", 2, gift=True)
            Cart.add(cart, "B2")
            Cart.parse("x", strict=True)
            ship(order, "post", "late", "fragile")
            ship(*extra)
            ship(**extra)
            unknown(order)
            cart.add("C3", 1, True)
        PY;

    public function test_each_parameter_reads_the_argument_it_receives_at_a_site(): void
    {
        $this->assertSame(['order' => 'order', 'carrier' => '"post"'], $this->argumentsAt(15));
        $this->assertSame(['order' => 'order', 'carrier' => '"dhl"'], $this->argumentsAt(16));
    }

    public function test_a_bound_call_skips_self_and_a_call_through_the_class_does_not(): void
    {
        $this->assertSame(['sku' => '"A1"', 'quantity' => '2', 'gift' => 'True'], $this->argumentsAt(17));
        $this->assertSame(['self' => 'cart', 'sku' => '"B2"'], $this->argumentsAt(18));
        $this->assertSame(['text' => '"x"', 'strict' => 'True'], $this->argumentsAt(19));
    }

    public function test_extra_positionals_land_in_the_star_parameter_first_come(): void
    {
        $this->assertSame(['order' => 'order', 'carrier' => '"post"', 'rest' => '"late"'], $this->argumentsAt(20));
    }

    public function test_a_keyword_only_parameter_takes_no_positional(): void
    {
        $this->assertSame(['sku' => '"C3"', 'quantity' => '1'], $this->argumentsAt(24));
    }

    public function test_an_unpacked_argument_or_an_unresolved_call_binds_nothing(): void
    {
        $this->assertNull($this->argumentsAt(21));
        $this->assertNull($this->argumentsAt(22));
        $this->assertNull($this->argumentsAt(23));
    }

    /**
     * @return array<string, string>|null
     */
    private function argumentsAt(int $line): ?array
    {
        $codebase = Codebase::fromString(self::SHOP, 'shop.py');
        $call = array_values(array_filter($codebase->whereCall()->get(), static fn (ExprMatch $match): bool => $match->line() === $line))[0];
        $source = $call->module->source;

        return $codebase->index()->argumentsAt($call->expr)->mapOr(
            null,
            static fn (array $bound): array => array_map(static fn (Expr $argument): string => substr($source, $argument->start, $argument->end - $argument->start), $bound),
        );
    }
}
