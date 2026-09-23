<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\LoopWrappedInIfDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A loop whose whole body is one `if` around two statements or more wants a `continue` guard; a
 * one-statement filter, an `if` with an `else`, a body with more than the `if`, and a search that
 * leaves the loop are not that shape.
 */
final class LoopWrappedInIfDetectorTest extends TestCase
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
            'a foreach whose body is one if' => ['foreach (var x in xs) { if (x > 0) { total += x; count++; } }', 1],
            'a while with an unbraced body' => ['var i = 0; while (i < xs.Count) if (xs[i] > 0) { total += xs[i]; i++; }', 1],
            'a for loop' => ['for (var i = 0; i < xs.Count; i++) { if (xs[i] > 0) { total += xs[i]; count++; } }', 1],
            'a one-statement filter' => ['foreach (var x in xs) { if (x > 0) { total += x; } }', 0],
            'an if with an else' => ['foreach (var x in xs) { if (x > 0) { total += x; count++; } else { count--; } }', 0],
            'a body with more than the if' => ['foreach (var x in xs) { count++; if (x > 0) { total += x; count++; } }', 0],
            'a search that leaves the loop' => ['foreach (var x in xs) { if (x > 0) { total = x; break; } }', 0],
        ];
    }

    #[DataProvider('loops')]
    public function test_flags_a_loop_body_wrapped_in_one_if(string $loop, int $flagged): void
    {
        $source = "using System.Collections.Generic;\n\npublic class Sums\n{\n    public int Add(List<int> xs)\n    {\n        var total = 0;\n        var count = 0;\n        {$loop}\n        return total + count;\n    }\n}\n";

        $this->assertCount($flagged, new LoopWrappedInIfDetector()->find(Codebase::fromString($source, 'Sums.cs')), $source);
    }
}
