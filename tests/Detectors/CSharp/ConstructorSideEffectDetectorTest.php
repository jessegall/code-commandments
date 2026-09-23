<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\ConstructorSideEffectDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A constructor that tells a collaborator it was handed to act, and throws the answer away, changes
 * something outside the object just by building it. Keeping an answer, a guard, the class's own helper
 * and a call tried in a `try` are not that.
 */
final class ConstructorSideEffectDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function constructors(): array
    {
        return [
            'a call on a handed parameter' => ['public Report(Printer printer) { this.printer = printer; printer.Print("start"); }', 1],
            'a call on a field filled from a parameter' => ['public Report(Printer printer) { this.printer = printer; this.printer.Print("start"); }', 1],
            'an answer kept' => ['public Report(Printer printer) { this.printer = printer; pages = printer.Pages(); }', 0],
            'a guard' => ['public Report(Printer printer) { ArgumentNullException.ThrowIfNull(printer); this.printer = printer; }', 0],
            'its own helper' => ['public Report(Printer printer) { this.printer = printer; Reset(); }', 0],
            'a call tried in a try' => ['public Report(Printer printer) { this.printer = printer; try { printer.Print("start"); } catch (InvalidOperationException e) { throw new ArgumentException("no printer", e); } }', 0],
        ];
    }

    #[DataProvider('constructors')]
    public function test_flags_a_constructor_that_acts_on_what_it_was_handed(string $constructor, int $flagged): void
    {
        $source = "using System;\n\npublic class Printer\n{\n    public void Print(string line) { }\n\n    public int Pages() => 0;\n}\n\npublic class Report\n{\n    private readonly Printer printer;\n\n    private int pages;\n\n    {$constructor}\n\n    private void Reset() => pages = 0;\n}\n";
        $this->assertCount($flagged, new ConstructorSideEffectDetector()->find(Codebase::fromString($source, 'Report.cs')), $source);
    }
}
