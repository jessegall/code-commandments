<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\FeatureEnvyDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A method that loops another object's collection or writes its fields, reaching into it more than into its own
 * state, holds behaviour exiled from that object; orchestration, a mapper, and a strategy filling a contract do not.
 */
final class FeatureEnvyDetectorTest extends TestCase
{
    use NeedsTheBridge;

    private const string TYPES = "using System.Collections.Generic;\nusing System.Linq;\npublic sealed class Line\n{\n    public decimal Price { get; init; }\n    public int Weight { get; init; }\n}\npublic sealed class Order\n{\n    public List<Line> Lines { get; } = [];\n    public bool Frozen { get; set; }\n    public int Strikes { get; set; }\n}\npublic sealed record OrderView(decimal Total);\npublic interface IPricing\n{\n    decimal Total(Order order);\n}\npublic sealed class Printer\n{\n    public void Print(Line line) { }\n}\n";

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function classes(): array
    {
        return [
            'looping its collection' => ["public sealed class Calculator\n{\n    public decimal Total(Order order)\n    {\n        decimal sum = 0;\n        foreach (var line in order.Lines)\n        {\n            sum += line.Price;\n        }\n        return sum;\n    }\n}", ['MethodDeclaration Total']],
            'writing its fields' => ["public sealed class Moderator\n{\n    public void Freeze(Order order)\n    {\n        order.Frozen = true;\n        order.Strikes++;\n    }\n}", ['MethodDeclaration Freeze']],
            'querying its collection' => ["public sealed class Scales\n{\n    public int Heavy(Order order) => order.Lines.Count(line => line.Weight > 10);\n}", ['MethodDeclaration Heavy']],
            'handing each element to its own collaborator' => ["public sealed class Receipts(Printer printer)\n{\n    public void PrintAll(Order order)\n    {\n        foreach (var line in order.Lines)\n        {\n            printer.Print(line);\n        }\n    }\n}", []],
            'a mapper building another type' => ["public sealed class Views\n{\n    public OrderView For(Order order)\n    {\n        decimal sum = 0;\n        foreach (var line in order.Lines)\n        {\n            sum += line.Price;\n        }\n        return new OrderView(sum);\n    }\n}", []],
            'a strategy filling a contract' => ["public sealed class StandardPricing : IPricing\n{\n    public decimal Total(Order order)\n    {\n        decimal sum = 0;\n        foreach (var line in order.Lines)\n        {\n            sum += line.Price;\n        }\n        return sum;\n    }\n}", []],
            'a partial type another part completes' => ["public sealed partial class TotalPipe\n{\n    public decimal Total(Order order)\n    {\n        decimal sum = 0;\n        foreach (var line in order.Lines)\n        {\n            sum += line.Price;\n        }\n        return sum;\n    }\n}", []],
            'a type deriving from a base' => ["public abstract class Step\n{\n}\npublic sealed class FreezeStep : Step\n{\n    public void Freeze(Order order)\n    {\n        order.Frozen = true;\n        order.Strikes++;\n    }\n}", []],
            'an anonymous shape built from it' => ["public sealed class Summaries\n{\n    public object For(Order order) => new { Count = order.Lines.Count(line => line.Weight > 10) };\n}", []],
            'a helper on the type it takes' => ["public sealed class Basket\n{\n    public List<Line> Lines { get; } = [];\n    public static decimal Total(Basket basket)\n    {\n        decimal sum = 0;\n        foreach (var line in basket.Lines)\n        {\n            sum += line.Price;\n        }\n        return sum;\n    }\n}", []],
            'reading more of its own state' => ["public sealed class Tariff(decimal rate, decimal floor, decimal cap)\n{\n    public decimal Charge(Order order)\n    {\n        var total = order.Lines.Sum(line => line.Price) * rate;\n        return total < floor ? floor : total > cap ? cap : rate * total;\n    }\n}", []],
        ];
    }

    /**
     * @param  list<string>  $flagged
     */
    #[DataProvider('classes')]
    public function test_flags_behaviour_exiled_from_the_object_it_works_on(string $class, array $flagged): void
    {
        $found = new FeatureEnvyDetector()->find(Codebase::fromString(self::TYPES . $class . "\n", 'Envy.cs'));

        $this->assertSame($flagged, array_map(static fn ($match): string => $match->scope(), $found), $class);
    }
}
