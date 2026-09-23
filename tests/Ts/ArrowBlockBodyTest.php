<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ts;

use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Expr\ExprKind;
use JesseGall\CodeCommandments\Ts\ModuleFile;
use JesseGall\CodeCommandments\Ts\Node\BlockStmt;
use JesseGall\CodeCommandments\Ts\Node\IfStmt;
use JesseGall\CodeCommandments\Ts\Node\ReturnStmt;
use JesseGall\CodeCommandments\Ts\Node\VariableDecl;
use PHPUnit\Framework\TestCase;

/**
 * An arrow's `{ … }` body is statements, not an object literal — and `async` in front of an arrow is
 * a modifier, not a function being called. Most functions in a `<script setup>` are written as
 * `const load = async () => { … }`, so a rule reading function bodies saw none of them.
 */
final class ArrowBlockBodyTest extends TestCase
{
    public function test_a_block_bodied_arrow_carries_its_statements(): void
    {
        $arrow = $this->initializerOf('const load = (page: number) => { if (page < 1) { return null; } return page; };');

        $this->assertSame(ExprKind::Arrow, $arrow->kind);
        $this->assertInstanceOf(BlockStmt::class, $arrow->get('block'));
        $this->assertInstanceOf(IfStmt::class, $arrow->get('block')->body[0]);
    }

    public function test_an_async_arrow_is_an_arrow_not_a_call(): void
    {
        $arrow = $this->initializerOf('const load = async (page) => { return page; };');

        $this->assertSame(ExprKind::Arrow, $arrow->kind);
        $this->assertInstanceOf(ReturnStmt::class, $arrow->get('block')->body[0]);
    }

    public function test_an_async_arrow_keeps_its_parameters_on_the_declaration(): void
    {
        $declaration = ModuleFile::fromFile('const load = async (page: number) => { return page; };', 'a.ts')->nodes()[0];

        $this->assertInstanceOf(VariableDecl::class, $declaration);
        $this->assertNull($declaration->initCall);
        $this->assertCount(1, $declaration->initParams ?? []);
    }

    public function test_an_expression_bodied_arrow_carries_no_block(): void
    {
        $arrow = $this->initializerOf('const double = (n: number) => n * 2;');

        $this->assertNull($arrow->get('block'));
        $this->assertSame(ExprKind::Binary, $arrow->get('body')->kind);
    }

    public function test_the_statements_of_a_callback_arrow_are_walked_as_nodes(): void
    {
        $module = ModuleFile::fromFile(<<<'TS'
            onMounted(async () => {
                if (ready) {
                    return;
                }
            });
            TS, 'a.ts');

        $kinds = array_map(static fn (object $node): string => $node::class, $module->nodes());

        $this->assertContains(IfStmt::class, $kinds);
        $this->assertContains(ReturnStmt::class, $kinds);
    }

    public function test_statements_inside_an_arrow_report_their_line_in_the_file(): void
    {
        $module = ModuleFile::fromFile("const a = 1;\nconst load = () => {\n    return a;\n};", 'a.ts');
        $returns = array_values(array_filter($module->nodes(), static fn (object $node): bool => $node instanceof ReturnStmt));

        $this->assertSame(3, $module->lineAt($returns[0]->start));
    }

    public function test_an_arrow_with_a_declared_return_type_is_an_arrow(): void
    {
        $arrow = $this->initializerOf('const label = (order: Order): Promise<string> => { return order.name; };');

        $this->assertSame(ExprKind::Arrow, $arrow->kind);
        $this->assertInstanceOf(ReturnStmt::class, $arrow->get('block')->body[0]);
    }

    public function test_a_function_expression_carries_its_statements_like_an_arrow(): void
    {
        $arrow = $this->initializerOf('const load = async function named(page: number): Promise<void> { return page; };');

        $this->assertSame(ExprKind::Arrow, $arrow->kind);
        $this->assertInstanceOf(ReturnStmt::class, $arrow->get('block')->body[0]);
    }

    public function test_a_ternary_on_a_group_is_not_mistaken_for_a_typed_arrow(): void
    {
        $this->assertSame(ExprKind::Conditional, $this->initializerOf('const x = ready ? (a) : b;')->kind);
    }

    public function test_a_for_of_loop_says_so_and_ranges_over_its_iterable(): void
    {
        $loop = \JesseGall\CodeCommandments\Ts\Parser::block('for await (const { id } of orders.value) { seen(id); }')->body[0];

        $this->assertInstanceOf(\JesseGall\CodeCommandments\Ts\Node\LoopStmt::class, $loop);
        $this->assertSame('for-of', $loop->keyword);
        $this->assertSame('orders.value', $loop->head[0]->source());
    }

    public function test_a_for_in_loop_says_so(): void
    {
        $this->assertSame('for-in', \JesseGall\CodeCommandments\Ts\Parser::block('for (const key in totals) { add(key); }')->body[0]->keyword);
    }

    private function initializerOf(string $source): Expr
    {
        $declaration = ModuleFile::fromFile($source, 'a.ts')->nodes()[0];
        $this->assertInstanceOf(VariableDecl::class, $declaration);

        return $declaration->initializer;
    }
}
