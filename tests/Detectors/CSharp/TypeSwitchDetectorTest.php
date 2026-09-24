<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\TypeSwitchDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A switch that asks which of the codebase's own types a value is before acting belongs as a member each type
 * answers; a switch over types the codebase does not own, over values, or in a named constructor is fine.
 */
final class TypeSwitchDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function members(): array
    {
        return [
            'a switch expression over own types' => ['public static double Area(Shape s) => s switch { Circle c => 3.14 * c.R * c.R, Square q => q.Side * q.Side, _ => 0 };', 1],
            'a switch statement over own types' => ['public static string Name(Shape s) { switch (s) { case Circle: return "circle"; case Square q when q.Side > 0: return "square"; default: return "?"; } }', 1],
            'dispatch on an object' => ['public static string Kind(object o) => o switch { Circle => "c", Square => "s", _ => "" };', 0],
            'a mapper building a new object per type' => ['public static Drawing Draw(Shape s) => s switch { Circle c => new Drawing(c.R), Square q => new Drawing(q.Side), _ => new Drawing(0) };', 0],
            'a closed union\'s own cases' => ['public abstract record Outcome { public sealed record Done : Outcome; public sealed record Failed(string Why) : Outcome; } public static string Label(Outcome o) => o switch { Outcome.Done => "done", Outcome.Failed f => f.Why, _ => "" };', 0],
            'types the codebase does not own' => ['public static string Kind(object o) => o switch { string t => t, int n => n.ToString(), _ => "" };', 0],
            'a switch over values' => ['public static int Rank(int n) => n switch { 0 => 1, 1 => 2, _ => 3 };', 0],
            'one type only' => ['public static double Radius(Shape s) => s switch { Circle c => c.R, _ => 0 };', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_switch_over_the_codebases_own_types(string $member, int $flagged): void
    {
        $source = "public abstract class Shape;\npublic sealed class Circle : Shape { public double R; }\npublic sealed class Square : Shape { public double Side; }\npublic sealed record Drawing(double Size);\npublic static class Geometry\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new TypeSwitchDetector()->find(Codebase::fromString($source, 'Geometry.cs')), $source);
    }
}
