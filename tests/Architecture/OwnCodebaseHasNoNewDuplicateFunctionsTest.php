<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Architecture;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DuplicateFunctionDetector;
use PHPUnit\Framework\TestCase;

/**
 * We eat our own dog food: this runs our {@see DuplicateFunctionDetector} over our OWN `src/`, so an
 * exact-duplicate method can never be ADDED without a test failing. The current duplicates are a documented
 * BASELINE (the cleanup backlog) — the test fails the moment a NEW one appears OR a listed one is removed,
 * so the list only shrinks. Consolidate a `Class::method` below (a shared helper/trait) and delete its line.
 */
final class OwnCodebaseHasNoNewDuplicateFunctionsTest extends TestCase
{
    /**
     * Known exact-duplicate methods, awaiting consolidation. NEVER add to this list — extract the shared
     * code instead. (Removing an entry as you consolidate is expected and keeps the baseline honest.) The
     * whole original backlog has now been consolidated, so this is empty: the codebase carries NO exact
     * duplicate method, and the test fails the instant one is added.
     *
     * @var list<string>
     */
    private const array BASELINE = [];

    public function test_no_exact_duplicate_functions_outside_the_shrinking_baseline(): void
    {
        $codebase = Codebase::scan(__DIR__ . '/../../src');

        $found = [];

        foreach (new DuplicateFunctionDetector()->find($codebase) as $match) {
            $found[(string) $match->scope()] = true;
        }

        $found = array_keys($found);
        sort($found);

        $new = array_values(array_diff($found, self::BASELINE));
        $fixed = array_values(array_diff(self::BASELINE, $found));

        $this->assertSame([], $new, "NEW exact-duplicate function(s) in src/ — extract the shared code (do NOT add to the baseline):\n" . implode("\n", $new));
        $this->assertSame([], $fixed, "These baseline duplicates are gone — delete them from BASELINE to keep it honest:\n" . implode("\n", $fixed));
    }
}
