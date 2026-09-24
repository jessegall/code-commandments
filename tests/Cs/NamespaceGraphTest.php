<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NamespaceGraph;
use JesseGall\CodeCommandments\DependencyArrow;
use PHPUnit\Framework\TestCase;

/**
 * Which namespace references which, read from what the compiler resolved: a type named in a declaration, a
 * value's type, a call's target and a type argument all count; a type the codebase does not declare and a
 * reference within one namespace do not.
 */
final class NamespaceGraphTest extends TestCase
{
    use NeedsTheBridge;

    private const string SOURCE = <<<'CS'
        namespace Shop.Pricing
        {
            public sealed record Money(int Cents);

            public static class Tax
            {
                public static Money Add(Money price) => new Money(price.Cents * 121 / 100);
            }
        }

        namespace Shop.Orders
        {
            using System.Collections.Generic;
            using Shop.Pricing;

            public sealed class Order
            {
                private readonly List<Money> lines = new();

                public Money Total() => Tax.Add(new Money(0));

                public string Label() => "order";
            }
        }
        CS;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    public function test_reads_which_namespace_references_which(): void
    {
        $this->assertSame(['global::Shop.Orders' => ['global::Shop.Pricing']], $this->graph()->arrows()->references());
    }

    public function test_every_arrow_is_named_where_it_is_written(): void
    {
        $arrows = $this->graph()->arrows()->all;

        $this->assertNotEmpty($arrows);
        $this->assertTrue(array_all($arrows, static fn (DependencyArrow $arrow): bool => $arrow->from === 'global::Shop.Orders' && $arrow->to === 'global::Shop.Pricing'));
        $this->assertContains(18, array_map(static fn (DependencyArrow $arrow): int => $arrow->at->line(), $arrows));
    }

    private function graph(): NamespaceGraph
    {
        return new NamespaceGraph(Codebase::fromString(self::SOURCE, 'Shop.cs'));
    }
}
