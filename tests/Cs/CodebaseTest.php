<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Bridge;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use PHPUnit\Framework\TestCase;

/**
 * The C# engine reads the bridge's tree behind the selectors every engine answers: functions and
 * statements found by what they are, calls carrying their resolved target, spans that point at the
 * right bytes. Run against the real bridge; skipped where `dotnet` is not installed.
 */
final class CodebaseTest extends TestCase
{
    private const string SOURCE = <<<'CS'
        using System.Collections.Generic;
        using System.Linq;

        namespace Shop;

        // Prijs in € — a non-ASCII character before the code, so a span off by a byte shows.
        public sealed class Cart
        {
            private readonly List<int> prices = [];

            public int Total()
            {
                int Sum() => prices.Sum();

                if (prices.Count == 0)
                {
                    return 0;
                }

                return Sum();
            }

            public string Label => $"{Total()} items";
        }
        CS;

    private static ?Codebase $codebase = null;

    public static function setUpBeforeClass(): void
    {
        if (Bridge::located()->isSome()) {
            self::$codebase = Codebase::fromString(self::SOURCE, 'Cart.cs');
        }
    }

    protected function setUp(): void
    {
        if (self::$codebase === null) {
            $this->markTestSkipped('needs the dotnet SDK to build the Roslyn bridge');
        }
    }

    public function test_functions_are_every_member_that_runs_a_body(): void
    {
        $this->assertSame(['MethodDeclaration Total', 'LocalFunctionStatement Sum', 'PropertyDeclaration Label'], $this->scopes(self::$codebase->whereFunction()->get()));
    }

    public function test_statements_are_found_by_what_they_are(): void
    {
        $kinds = array_map(static fn (NodeMatch $match): string => $match->node->kind, self::$codebase->whereStatement()->get());

        $this->assertContains('IfStatement', $kinds);
        $this->assertSame(2, count(array_keys($kinds, 'ReturnStatement', true)));
    }

    public function test_a_call_carries_the_method_the_compiler_resolved(): void
    {
        $sum = array_values(array_filter(self::$codebase->whereCall()->get(), static fn (NodeMatch $call): bool => $call->node->target?->name === 'Sum'));

        $this->assertNotSame([], $sum);
        $this->assertSame('global::System.Linq.Enumerable', array_values(array_filter($sum, static fn (NodeMatch $call): bool => $call->node->target->type !== 'global::Shop.Cart'))[0]->node->target->type);
    }

    public function test_a_span_points_at_the_bytes_of_its_node(): void
    {
        $if = self::$codebase->whereStatement()->kindIs('IfStatement')->get()[0];

        $this->assertStringStartsWith('if (prices.Count == 0)', $if->span()->text());
        $this->assertSame(15, $if->line());
    }

    public function test_an_expression_bodied_member_has_its_expression_as_its_body_and_its_ancestors_lead_out(): void
    {
        $label = self::$codebase->whereFunction()->kindIs('PropertyDeclaration')->get()[0];
        $module = self::$codebase->modules()[0];

        $this->assertTrue($label->node->functionBody()->isSomeAnd(static fn ($body): bool => $body->is('ArrowExpressionClause')));
        $this->assertSame(['ClassDeclaration', 'FileScopedNamespaceDeclaration', 'CompilationUnit'], array_map(static fn ($node): string => $node->kind, $module->ancestorsOf($label->node)));
    }

    /**
     * @param  list<NodeMatch>  $matches
     * @return list<string>
     */
    private function scopes(array $matches): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->scope(), $matches);
    }
}
