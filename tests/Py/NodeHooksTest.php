<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\Jump;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Return_;
use JesseGall\CodeCommandments\Py\Parser;
use PHPUnit\Framework\TestCase;

/**
 * A Python node answers the walk hooks every engine's nodes answer — what it contains, the expressions
 * it holds, which of its kind it is, what it declares, the body it runs as a function — because a
 * tool that reads a tree (a structural fingerprint, a nesting count) reads only these.
 */
final class NodeHooksTest extends TestCase
{
    private const string SOURCE = <<<'PY'
        def load(rows, limit=10):
            for row in rows:
                if row.skip:
                    continue
                while row.pending:
                    try:
                        row.pop()
                    except KeyError:
                        break
            return rows[:limit]
        PY;

    public function test_descendants_reach_every_nested_statement(): void
    {
        $kinds = array_map(static fn (Node $node): string => new \ReflectionClass($node)->getShortName(), $this->function()->descendants());

        foreach (['Param', 'Block', 'ForLoop', 'IfStmt', 'Jump', 'WhileLoop', 'TryStmt', 'ExprStmt', 'ExceptHandler', 'Return_'] as $kind) {
            $this->assertContains($kind, $kinds);
        }
    }

    public function test_the_variant_tells_nodes_of_one_kind_apart(): void
    {
        $jumps = array_values(array_filter($this->function()->descendants(), static fn (Node $node): bool => $node instanceof Jump));

        $this->assertSame(['continue', 'break'], array_map(static fn (Jump $jump): string => $jump->variant(), $jumps));
    }

    public function test_a_node_holds_only_its_own_expressions(): void
    {
        $return = array_values(array_filter($this->function()->descendants(), static fn (Node $node): bool => $node instanceof Return_))[0];

        $this->assertCount(1, $return->expressions());
        $this->assertSame([], $this->function()->body->expressions());
    }

    public function test_a_function_declares_its_name_and_each_parameter_its_own(): void
    {
        $function = $this->function();

        $this->assertSame(['load'], $function->declaredNames());
        $this->assertSame(['rows'], $function->params[0]->declaredNames());
        $this->assertInstanceOf(Expr::class, $function->params[1]->expressions()[0]);
    }

    public function test_only_a_function_has_a_function_body(): void
    {
        $function = $this->function();

        $this->assertSame($function->body, $function->functionBody()->unwrap());
        $this->assertTrue($function->body->functionBody()->isNone());
    }

    private function function(): FunctionDef
    {
        $function = Parser::module(self::SOURCE)->body[0];
        $this->assertInstanceOf(FunctionDef::class, $function);

        return $function;
    }
}
