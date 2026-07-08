<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Architecture;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DuplicateFunctionDetector;
use JesseGall\CodeCommandments\Detectors\Backend\NearDuplicateFunctionDetector;
use PHPUnit\Framework\TestCase;

/**
 * We eat our own dog food: this runs our {@see DuplicateFunctionDetector} (exact) AND
 * {@see NearDuplicateFunctionDetector} (type-2 clones — same shape, different names/literals) over our OWN
 * `src/`, so neither a byte-identical NOR a near-identical method can be ADDED without a test failing. Both
 * baselines are now EMPTY: the codebase carries no such duplicate, and the test fails the instant one
 * appears. Consolidate the offender into a shared helper/trait rather than adding it to a baseline.
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

    /**
     * Known near-duplicate methods (type-2 clones), awaiting consolidation. Empty — our own src/ carries
     * none. NEVER add to this list; parameterise the shared shape into one method instead.
     *
     * @var list<string>
     */
    private const array NEAR_BASELINE = [];

    public function test_no_near_duplicate_functions_outside_the_shrinking_baseline(): void
    {
        $codebase = Codebase::scan(__DIR__ . '/../../src');

        $found = [];

        foreach (new NearDuplicateFunctionDetector()->find($codebase) as $match) {
            $found[(string) $match->scope()] = true;
        }

        $found = array_keys($found);
        sort($found);

        $new = array_values(array_diff($found, self::NEAR_BASELINE));
        $fixed = array_values(array_diff(self::NEAR_BASELINE, $found));

        $this->assertSame([], $new, "NEW near-duplicate function(s) in src/ — parameterise the shared shape into one method (do NOT add to the baseline):\n" . implode("\n", $new));
        $this->assertSame([], $fixed, "These near-duplicate baseline entries are gone — delete them to keep it honest:\n" . implode("\n", $fixed));
    }
}
