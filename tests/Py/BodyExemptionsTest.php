<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The shapes a clone rule leaves alone, read off a Python function — named as the other engines name
 * them: a constructor, a body that is one return or one call (a docstring aside), a lookup table written as code, and a
 * stub that only stands in for a body.
 */
final class BodyExemptionsTest extends TestCase
{
    public function test_an_init_method_is_a_constructor_and_a_module_level_init_is_not(): void
    {
        $this->assertTrue($this->only("class Cart:\n    def __init__(self):\n        self.lines = []\n")->isConstructorDeclaration());
        $this->assertFalse($this->only("def __init__():\n    return 1\n")->isConstructorDeclaration());
        $this->assertFalse($this->only("class Cart:\n    def clear(self):\n        self.lines = []\n")->isConstructorDeclaration());
    }

    public function test_a_body_of_one_return_with_a_value_is_a_sole_return_expression(): void
    {
        $this->assertTrue($this->only("def total(cart):\n    return sum(cart.lines)\n")->isSoleReturnExpression());
        $this->assertTrue($this->only("def total(cart):\n    \"\"\"Doc.\"\"\"\n    return 1\n")->isSoleReturnExpression());
        $this->assertFalse($this->only("def stop():\n    return\n")->isSoleReturnExpression());
        $this->assertFalse($this->only("def total(cart):\n    x = 1\n    return x\n")->isSoleReturnExpression());
    }

    public function test_a_body_of_one_call_is_a_sole_expression_statement(): void
    {
        $this->assertTrue($this->only("def save(order):\n    order.store()\n")->isSoleExpressionStatement());
        $this->assertFalse($this->only("def save(order):\n    order.status = 'saved'\n")->isSoleExpressionStatement());
    }

    public function test_a_table_of_constant_returns_that_calls_nothing_is_a_literal_lookup(): void
    {
        $table = "def colour(status):\n    if status == 'paid':\n        return 'green'\n    if status == 'late':\n        return 'red'\n    return None\n";

        $this->assertTrue($this->only($table)->isLiteralLookup());
        $this->assertFalse($this->only(str_replace("return 'red'", "return red()", $table))->isLiteralLookup());
        $this->assertFalse($this->only(str_replace("return 'red'", "return f'{status}'", $table))->isLiteralLookup());
        $this->assertFalse($this->only("def log(x):\n    print(x)\n")->isLiteralLookup());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function stubs(): iterable
    {
        yield 'pass' => ["def handle(self):\n    pass\n"];
        yield 'ellipsis' => ["def handle(self): ...\n"];
        yield 'docstring alone' => ["def handle(self):\n    \"\"\"Handle it.\"\"\"\n"];
        yield 'docstring then ellipsis' => ["def handle(self):\n    \"\"\"Handle it.\"\"\"\n    ...\n"];
        yield 'raise NotImplementedError' => ["def handle(self):\n    raise NotImplementedError\n"];
        yield 'raise NotImplementedError()' => ["def handle(self):\n    raise NotImplementedError('subclasses handle it')\n"];
    }

    #[DataProvider('stubs')]
    public function test_a_body_of_placeholders_is_a_stub(string $source): void
    {
        $this->assertTrue($this->only($source)->isStub());
    }

    public function test_a_body_that_does_something_is_no_stub(): void
    {
        $this->assertFalse($this->only("def handle(self):\n    raise ValueError('no')\n")->isStub());
        $this->assertFalse($this->only("def handle(self):\n    pass\n    self.done = True\n")->isStub());
        $this->assertFalse($this->only("def handle(self):\n    raise\n")->isStub());
    }

    private function only(string $source): NodeMatch
    {
        return Codebase::fromString($source)->whereFunction()->get()[0];
    }
}
