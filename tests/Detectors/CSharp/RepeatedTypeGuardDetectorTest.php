<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\RepeatedTypeGuardDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The same chain of two or more type checks narrowing a value, written at two or more sites, is a shape with
 * no name; a chain written once, a single type check, and null tests are fine.
 */
final class RepeatedTypeGuardDetectorTest extends TestCase
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
            'the same narrowing twice' => ['public static bool A(Shape s) => s is Box b && b.Lid is Hinged; public static int B(Shape s) { if (s is Box b && b.Lid is Hinged) { return 1; } return 0; }', 2],
            'typed property patterns twice' => ['public static bool A(Shape s, Lid l) => s is Box { Lid: not null } && l is Hinged; public static bool B(Shape s, Lid l) => s is Box { Lid: not null } && l is Hinged;', 2],
            'asked once' => ['public static bool A(Shape s) => s is Box b && b.Lid is Hinged;', 0],
            'a single type check twice' => ['public static bool A(Shape s) => s is Box; public static bool B(Shape s) => s is Box;', 0],
            'not-null captures, not types' => ['public static bool A(Box? a, Box? b) => a is { } x && b is { } y; public static bool B(Box? a, Box? b) => a is { } x && b is { } y;', 0],
            'null tests, not types' => ['public static bool A(Box? a, Box? b) => a is null && b is null; public static bool B(Box? a, Box? b) => a is null && b is null;', 0],
        ];
    }

    #[DataProvider('bodies')]
    public function test_flags_a_type_narrowing_chain_written_at_several_sites(string $body, int $flagged): void
    {
        $source = "public abstract class Shape;\npublic abstract class Lid;\npublic sealed class Hinged : Lid;\npublic sealed class Box : Shape { public Lid? Lid; }\npublic static class Shapes\n{\n    {$body}\n}\n";
        $this->assertCount($flagged, new RepeatedTypeGuardDetector()->find(Codebase::fromString($source, 'Shapes.cs')), $source);
    }
}
