<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

/**
 * Which calls reach a `def`: through absolute and relative imports, aliased or not, through `self`,
 * through a parameter or variable annotated with a class, and up a class's bases — and a call the
 * index cannot resolve is left out rather than guessed.
 */
final class CallIndexTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-py-index-' . uniqid();
        $files = [
            'shop/__init__.py' => '',
            'shop/money.py' => "def rounded(amount):\n    return round(amount, 2)\n",
            'shop/orders.py' => <<<'PY'
                from shop.money import rounded as r


                class Base:
                    def total(self):
                        return 0


                class Order(Base):
                    def lines(self):
                        return []

                    def summary(self):
                        return (self.total(), r(self.subtotal()), self.lines())


                def describe(order: Order):
                    return order.lines()


                def describe_later(order: "Order"):
                    current: Order = order
                    return current.lines()
                PY,
            'shop/checkout.py' => <<<'PY'
                import shop.money
                import shop.money as cash
                from . import money
                from .orders import Order


                def pay(order: Order, amount):
                    shop.money.rounded(amount)
                    cash.rounded(amount)
                    money.rounded(amount)
                    order.lines()
                    Order.lines(order)
                    unknown.rounded(amount)
                PY,
        ];

        foreach ($files as $path => $source) {
            @mkdir(dirname("{$this->root}/{$path}"), 0777, true);
            file_put_contents("{$this->root}/{$path}", $source);
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_function_is_reached_through_every_spelling_of_its_import(): void
    {
        $this->assertSame(
            ['checkout.py:8', 'checkout.py:9', 'checkout.py:10', 'orders.py:14'],
            $this->callersOf('rounded'),
        );
    }

    public function test_a_method_is_reached_through_self_annotations_and_its_class(): void
    {
        $this->assertSame(
            ['checkout.py:11', 'checkout.py:12', 'orders.py:14', 'orders.py:18', 'orders.py:23'],
            $this->callersOf('lines'),
        );
    }

    public function test_an_inherited_method_is_reached_through_the_subclass(): void
    {
        $this->assertSame(['orders.py:14'], $this->callersOf('total'));
    }

    /**
     * @return list<string>
     */
    private function callersOf(string $function): array
    {
        $codebase = Codebase::scan($this->root);
        $def = array_values(array_filter($codebase->whereFunction()->get(), static fn (NodeMatch $match): bool => $match->name() === $function))[0]->node;
        $this->assertInstanceOf(FunctionDef::class, $def);
        $sites = array_map(static fn (ExprMatch $call): string => basename($call->file()) . ':' . $call->line(), $codebase->index()->callersOf($def));
        sort($sites, SORT_NATURAL);

        return $sites;
    }
}
