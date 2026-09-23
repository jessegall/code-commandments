<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\RedundantElseDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An `else` after a branch that already left says nothing the exit did not; an `else` after a branch
 * that falls through is a real decision, and an `else if` chain is a ladder.
 */
final class RedundantElseDetectorTest extends TestCase
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
            'an else after a return' => ["if (x > 0) { return 1; } else { x++; }", 1],
            'an else after a throw' => ["if (x < 0) { throw new System.ArgumentException(); } else { x++; }", 1],
            'an else after an unbraced return' => ["if (x > 0) return 1; else x++;", 1],
            'an else after a continue in a loop' => ["foreach (var y in new[] { 1 }) { if (y > x) { continue; } else { x += y; } }", 1],
            'an else after a branch that falls through' => ["if (x > 0) { x--; } else { x++; }", 0],
            'an else-if chain' => ["if (x > 0) { return 1; } else if (x < 0) { return -1; } else { x++; }", 0],
            'an if with no else' => ["if (x > 0) { return 1; }", 0],
        ];
    }

    #[DataProvider('bodies')]
    public function test_flags_an_else_after_an_exit(string $body, int $flagged): void
    {
        $source = "public class Counter\n{\n    public int Count(int x)\n    {\n        {$body}\n        return x;\n    }\n}\n";

        $this->assertCount($flagged, new RedundantElseDetector()->find(Codebase::fromString($source, 'Counter.cs')), $source);
    }
}
