<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\NamespaceCycleDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\TestCase;

/**
 * Two of the project's namespaces that each use the other are one namespace split under two names; the
 * finding sits on the thinner direction, the one worth cutting.
 */
final class NamespaceCycleDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    public function test_flags_the_thinner_direction_of_two_namespaces_that_use_each_other(): void
    {
        $source = <<<'CS'
            namespace Shop.Pricing
            {
                public sealed record Money(int Cents)
                {
                    public Shop.Orders.Order Sample() => new Shop.Orders.Order(this);
                }
            }

            namespace Shop.Orders
            {
                using Shop.Pricing;

                public sealed record Order(Money Total)
                {
                    public Money Doubled() => new Money(Total.Cents * 2);
                    public Money Halved() => new Money(Total.Cents / 2);
                }
            }
            CS;

        $found = new NamespaceCycleDetector()->find(Codebase::fromString($source, 'Shop.cs'));

        $this->assertSame([5], array_map(static fn ($match): int => $match->line(), $found));
    }

    public function test_leaves_references_that_point_one_way(): void
    {
        $source = <<<'CS'
            namespace Shop.Pricing
            {
                public sealed record Money(int Cents);
            }

            namespace Shop.Orders
            {
                using Shop.Pricing;

                public sealed record Order(Money Total)
                {
                    public Money Doubled() => new Money(Total.Cents * 2);
                }
            }
            CS;

        $this->assertSame([], new NamespaceCycleDetector()->find(Codebase::fromString($source, 'Shop.cs')));
    }

    public function test_leaves_a_namespace_and_its_own_nested_namespace(): void
    {
        $source = <<<'CS'
            namespace Shop.Orders
            {
                public sealed record Order(Shop.Orders.Lines.Line First);
            }

            namespace Shop.Orders.Lines
            {
                public sealed record Line(int Cents)
                {
                    public Shop.Orders.Order Alone() => new Shop.Orders.Order(this);
                }
            }
            CS;

        $this->assertSame([], new NamespaceCycleDetector()->find(Codebase::fromString($source, 'Shop.cs')));
    }
}
