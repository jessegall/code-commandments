<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\ConvertedArgumentDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A scalar parameter its callers keep filling with the same conversion — `.ToString()`, a cast, `int.Parse` —
 * asks for the converted form instead of the value; a conversion only a few of its callers make is their own
 * business.
 */
final class ConvertedArgumentDetectorTest extends TestCase
{
    use NeedsTheBridge;

    private const string RECEIPTS = "public sealed class Order\n{\n    public int Id { get; init; }\n    public double Total { get; init; }\n    public int Lines { get; init; }\n}\npublic sealed class Receipts\n{\n    public string For(string orderId) => \"receipt-\" + orderId;\n    public decimal Charge(decimal amount) => amount;\n}\n";

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, list<int>}>
     */
    public static function callers(): array
    {
        return [
            'every caller converts an id to text' => ["public string A(Order order) => receipts.For(order.Id.ToString());\n    public string B(Order order) => receipts.For(order.Id.ToString());", [14, 15]],
            'every caller casts to decimal' => ["public decimal A(Order order) => receipts.Charge((decimal) order.Total);\n    public decimal B(Order order) => receipts.Charge((decimal) (order.Total * 2));", [14, 15]],
            'one caller converts' => ["public string A(Order order) => receipts.For(order.Id.ToString());\n    public string B(string id) => receipts.For(id);", []],
            'a library method' => ["public double A(Order order) => System.Math.Log10((double) order.Id);\n    public double B(Order order) => System.Math.Log10((double) order.Lines);", []],
            'text finished by a builder' => ["public string A(System.Text.StringBuilder text) => receipts.For(text.ToString());\n    public string B(System.Text.StringBuilder text) => receipts.For(text.Append('!').ToString());", []],
            'a constant spelled in another type' => ["public string A() => receipts.For(42.ToString());\n    public string B() => receipts.For(7.ToString());", []],
            'a minority converts' => ["public string A(Order order) => receipts.For(order.Id.ToString());\n    public string B(Order order) => receipts.For(order.Id.ToString());\n    public string C(string id) => receipts.For(id);\n    public string D(string id) => receipts.For(id + \"x\");\n    public string E(string id) => receipts.For(id.Trim());", []],
        ];
    }

    /**
     * @param  list<int>  $lines
     */
    #[DataProvider('callers')]
    public function test_flags_a_parameter_its_callers_keep_converting_for(string $methods, array $lines): void
    {
        $source = self::RECEIPTS . "public sealed class Screen(Receipts receipts)\n{\n    {$methods}\n}\n";
        $found = new ConvertedArgumentDetector()->find(Codebase::fromString($source, 'Screen.cs'));

        $this->assertSame($lines, array_map(static fn (Located $match): int => $match->line(), $found), $source);
    }
}
