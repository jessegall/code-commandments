<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\CoalescedLoopSubjectDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A `foreach` over `items ?? []` hides the question of whether the collection is there inside the loop
 * header; a guard says it. A real fallback and a plain loop are fine.
 */
final class CoalescedLoopSubjectDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function loops(): array
    {
        return [
            'an empty collection expression' => ['foreach (var sku in skus ?? []) { Console.WriteLine(sku); }', 1],
            'Enumerable.Empty' => ['foreach (var sku in skus ?? Enumerable.Empty<string>()) { Console.WriteLine(sku); }', 1],
            'a new empty list' => ['foreach (var sku in skus ?? new List<string>()) { Console.WriteLine(sku); }', 1],
            'a real fallback' => ['foreach (var sku in skus ?? defaults) { Console.WriteLine(sku); }', 0],
            'a guarded loop' => ['if (skus is null) { return; } foreach (var sku in skus) { Console.WriteLine(sku); }', 0],
        ];
    }

    #[DataProvider('loops')]
    public function test_flags_a_loop_over_a_collection_defaulted_to_empty(string $loop, int $flagged): void
    {
        $source = "using System;\nusing System.Collections.Generic;\nusing System.Linq;\n\npublic class Picking\n{\n    private readonly List<string> defaults = [\"A1\"];\n\n    public void Print(List<string>? skus)\n    {\n        {$loop}\n    }\n}\n";
        $this->assertCount($flagged, new CoalescedLoopSubjectDetector()->find(Codebase::fromString($source, 'Picking.cs')), $source);
    }
}
