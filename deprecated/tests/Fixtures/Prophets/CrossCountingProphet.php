<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Prophets;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;

/**
 * Test fixture: a CROSS-file prophet (needs the codebase index). Counts judge()
 * calls so the findings-cache split can prove cross-file findings are re-run on
 * ANY sibling change (generation-keyed), unlike single-file findings.
 */
class CrossCountingProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    public static int $calls = 0;

    private ?CodebaseIndex $index = null;

    public static function reset(): void
    {
        self::$calls = 0;
    }

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Cross-file judge() counter';
    }

    public function detailedDescription(): string
    {
        return 'Counts judge() calls; needs the codebase index; flags FLAG_ME.';
    }

    public function judge(string $filePath, string $content): Judgment
    {
        self::$calls++;

        if (! str_contains($content, 'FLAG_ME')) {
            return $this->righteous();
        }

        return Judgment::withWarnings([
            $this->warningAt(1, 'cross FLAG_ME', 'FLAG_ME', 'cross:marker'),
        ]);
    }
}
