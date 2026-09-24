<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\RepeatedGuardDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The same compound condition about an object's data, asked at two or more sites, is a question with no name;
 * a condition asked once, one written differently, and a pair of type checks are fine.
 */
final class RepeatedGuardDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function bodies(): array
    {
        return [
            'the same guard in two methods' => ['public bool CanShip(Order order) => order.Paid && !order.Cancelled; public void Ship(Order order) { if (order.Paid && !order.Cancelled) { order.Lines.Clear(); } }', 2],
            'the same guard, conjuncts swapped' => ['public bool CanShip(Order order) => order.Paid && !order.Cancelled; public int Count(Order order) => !order.Cancelled && order.Paid ? 1 : 0;', 2],
            'asked once' => ['public bool CanShip(Order order) => order.Paid && !order.Cancelled;', 0],
            'two different guards' => ['public bool CanShip(Order order) => order.Paid && !order.Cancelled; public bool CanRefund(Order order) => order.Paid && order.Cancelled;', 0],
            'stored, not asked' => ['public bool A(Order order) { var ok = order.Paid && !order.Cancelled; return ok; } public bool B(Order order) { var ok = order.Paid && !order.Cancelled; return !ok; }', 0],
        ];
    }

    #[DataProvider('bodies')]
    public function test_flags_a_compound_condition_asked_at_several_sites(string $body, int $flagged): void
    {
        $source = "using System.Collections.Generic;\npublic sealed class Order { public bool Paid; public bool Cancelled; public List<string> Lines = new(); }\npublic static class Shipping\n{\n    {$body}\n}\n";
        $this->assertCount($flagged, new RepeatedGuardDetector()->find(Codebase::fromString($source, 'Shipping.cs')), $source);
    }
}
