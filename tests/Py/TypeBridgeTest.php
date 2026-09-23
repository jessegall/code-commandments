<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\Type;
use JesseGall\CodeCommandments\Py\TypeBridge;
use JesseGall\CodeCommandments\Support\Directory;
use PHPUnit\Framework\TestCase;

final class TypeBridgeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        if (TypeBridge::located()->isNone()) {
            $this->markTestSkipped('there is no python3, so there is no mypy bridge to type Python with');
        }

        $this->root = sys_get_temp_dir() . '/type-bridge-' . bin2hex(random_bytes(4));
        mkdir("{$this->root}/shop", 0777, true);
        touch("{$this->root}/shop/__init__.py");
        file_put_contents("{$this->root}/shop/cart.py", "class Cart:\n    def __init__(self) -> None:\n        self.lines: list[int] = []\n\n    def owner(self) -> 'str | None':\n        return None\n");
        file_put_contents("{$this->root}/shop/checkout.py", "from shop.cart import Cart\n\n\ndef make() -> Cart:\n    return Cart()\n\n\ndef total():\n    cart = make()\n    return cart.lines, cart.owner()\n");
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            Directory::delete($this->root);
        }
    }

    public function test_an_expression_reads_the_type_mypy_resolved_across_modules(): void
    {
        $codebase = Codebase::scan($this->root);

        $this->assertSame('shop.cart.Cart', $this->typeOf($codebase, 'cart', 10)->className()->unwrap());
        $this->assertSame('builtins.list', $this->typeOf($codebase, 'cart.lines', 10)->className()->unwrap());
        $this->assertTrue($this->typeOf($codebase, 'cart.owner()', 10)->nullable);
        $this->assertSame('builtins.str', $this->typeOf($codebase, 'cart.owner()', 10)->className()->unwrap());
    }

    public function test_a_name_that_builds_a_class_says_which_and_a_function_does_not(): void
    {
        $codebase = Codebase::scan($this->root);

        $this->assertSame('shop.cart.Cart', $this->typeOf($codebase, 'Cart', 5)->constructedClass()->unwrap());
        $this->assertTrue($this->typeOf($codebase, 'make', 9)->constructedClass()->isNone());
    }

    public function test_a_module_shadowing_the_standard_library_leaves_the_rest_typed(): void
    {
        file_put_contents("{$this->root}/logging.py", "def warn() -> None:\n    pass\n");

        $this->assertSame('shop.cart.Cart', $this->typeOf(Codebase::scan($this->root), 'cart', 10)->className()->unwrap());
    }

    public function test_a_codebase_built_from_strings_is_untyped(): void
    {
        $codebase = Codebase::fromString("x = 1\n");

        $this->assertTrue($codebase->whereExpression(static fn (): bool => true)->get()[0]->typeIn($codebase)->isNone());
    }

    private function typeOf(Codebase $codebase, string $text, int $line): Type
    {
        $match = array_values(array_filter(
            $codebase->whereExpression(static fn (): bool => true)->get(),
            static fn (ExprMatch $match): bool => $match->line() === $line && substr($match->module->source, $match->expr->start, $match->expr->end - $match->expr->start) === $text,
        ))[0];

        return $match->typeIn($codebase)->unwrap();
    }
}
