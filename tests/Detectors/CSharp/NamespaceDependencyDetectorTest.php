<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\NamespaceDependencyDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\TestCase;

/**
 * A declared layer may only use the layers it said it may use: a reference back up the stack or sideways is
 * flagged once per file and namespace it reaches; a reference down the stack, within a layer, into a namespace
 * nobody declared, or with no layers declared at all, is not.
 */
final class NamespaceDependencyDetectorTest extends TestCase
{
    use NeedsTheBridge;

    private const string SOURCE = <<<'CS'
        namespace Shop.Domain
        {
            public sealed record Order(int Cents)
            {
                public Shop.Web.OrderPage Page() => new Shop.Web.OrderPage(this);
                public Shop.Web.OrderPage Preview() => new Shop.Web.OrderPage(this);
            }
        }

        namespace Shop.Domain.Lines
        {
            public sealed record Line(Shop.Domain.Order Order, Shop.Logging.Trace Trace);
        }

        namespace Shop.Web
        {
            public sealed record OrderPage(Shop.Domain.Order Order);
        }

        namespace Shop.Logging
        {
            public sealed record Trace(string Text);
        }
        CS;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    public function test_flags_a_reference_a_layer_did_not_declare_once_per_file_and_target(): void
    {
        $detector = new NamespaceDependencyDetector()
            ->layer('Shop.Domain')
            ->layer('Shop.Web', mayUse: ['Shop.Domain']);

        $this->assertSame([5], $this->lines($detector));
    }

    public function test_declares_nothing_and_flags_nothing(): void
    {
        $this->assertSame([], $this->lines(new NamespaceDependencyDetector()));
    }

    /**
     * @return list<int>
     */
    private function lines(NamespaceDependencyDetector $detector): array
    {
        return array_map(static fn (Located $found): int => $found->line(), $detector->find(Codebase::fromString(self::SOURCE, 'Shop.cs')));
    }
}
