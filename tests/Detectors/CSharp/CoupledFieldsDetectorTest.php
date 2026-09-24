<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\CoupledFieldsDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A type whose own value fields always travel together — assembled into one value again and again, null-checked
 * together, or one copying what a sibling field already holds — holds one concept as several fields.
 */
final class CoupledFieldsDetectorTest extends TestCase
{
    use NeedsTheBridge;

    private const string TYPES = "using System;\npublic sealed record Window(DateOnly Start, DateOnly End);\npublic sealed record Workflow(Guid Id, string Name);\npublic sealed class Clock { }\n";

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function types(): array
    {
        return [
            'a pair assembled twice' => ["public sealed class Booking\n{\n    private DateOnly start;\n    private DateOnly end;\n    private string guest = \"\";\n    public Window Stay() => new Window(start, end);\n    public (DateOnly, DateOnly) Span() => (start, end);\n}", ['ClassDeclaration Booking']],
            'a pair null-checked together' => ["public sealed class Booking\n{\n    private DateOnly? start;\n    private DateOnly? end;\n    private string guest = \"\";\n    public bool IsOpen() => start is null || end is null;\n    public Window Stay() => new Window(start!.Value, end!.Value);\n}", ['ClassDeclaration Booking']],
            'a field mirroring a sibling' => ["public sealed class Run\n{\n    public Workflow Workflow { get; init; } = new(Guid.Empty, \"\");\n    public Guid WorkflowId { get; init; }\n    public int Step { get; set; }\n}", ['ClassDeclaration Run']],
            'assembled once' => ["public sealed class Booking\n{\n    private DateOnly start;\n    private DateOnly end;\n    private string guest = \"\";\n    public Window Stay() => new Window(start, end);\n}", []],
            'a value rebuilding itself' => ["public sealed record Price(decimal Amount, string Currency, bool Estimated)\n{\n    public Price Doubled() => new Price(Amount * 2, Currency, Estimated);\n    public Price Halved() => new Price(Amount / 2, Currency, Estimated);\n}", []],
            'every field projected' => ["public sealed class Booking\n{\n    private DateOnly start;\n    private DateOnly end;\n    public Window Stay() => new Window(start, end);\n    public (DateOnly, DateOnly) Span() => (start, end);\n}", []],
            'collaborators, not values' => ["public sealed class Booking\n{\n    private Clock first = new();\n    private Clock second = new();\n    private string guest = \"\";\n    public (Clock, Clock) Pair() => (first, second);\n    public Clock[] Both() => [first, second];\n}", []],
        ];
    }

    /**
     * @param  list<string>  $flagged
     */
    #[DataProvider('types')]
    public function test_flags_a_type_whose_fields_are_one_value(string $type, array $flagged): void
    {
        $found = new CoupledFieldsDetector()->find(Codebase::fromString(self::TYPES . $type . "\n", 'Booking.cs'));

        $this->assertSame($flagged, array_map(static fn ($match): string => $match->scope(), $found), $type);
    }
}
