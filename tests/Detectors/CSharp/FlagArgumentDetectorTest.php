<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\FlagArgumentDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A method whose whole body is a two-way branch on one of its `bool` parameters is two methods sharing a
 * name; a guard, a flag that is stored, an optional input handled once, and a branch on state are fine.
 */
final class FlagArgumentDetectorTest extends TestCase
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
            'an if-else on a bool' => ['public string Render(string order, bool compact) { if (compact) { return order; } else { return order + "!"; } }', 1],
            'a returned conditional on a negated bool' => ['public string Render(string order, bool full) => !full ? order : order + "!";', 1],
            'a statement body on a bool' => ['public void Log(string line, bool loud) { if (loud) { shown = true; } else { shown = line != ""; } }', 1],
            'a branch on an optional input' => ['public int Count(string? kind = null) { if (kind is null) { return 0; } else { return kind.Length; } }', 0],
            'a mapping that carries null through' => ['public string? Label(string? name) => name is null ? null : name.ToUpper();', 0],
            'a guard, then the work' => ['public string Render(string order, bool force) { if (force) { return order; } return order + "!"; }', 0],
            'a lookup of the flag\'s value' => ['public int Discount(bool member) => member ? 5 : 0;', 0],
            'a flag that is stored' => ['public void SetVisible(bool visible) { shown = visible; }', 0],
            'a branch on state' => ['public string Render(string order) => shown ? order : "";', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_method_that_a_parameter_switches_between_two_jobs(string $member, int $flagged): void
    {
        $source = "public class Printer\n{\n    private bool shown;\n    {$member}\n}\n";
        $this->assertCount($flagged, new FlagArgumentDetector()->find(Codebase::fromString($source, 'Printer.cs')), $source);
    }
}
