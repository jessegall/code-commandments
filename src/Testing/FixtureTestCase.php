<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Detectors\ChainDetector;
use PHPUnit\Framework\TestCase;

/**
 * Self-checking-fixture test shared by both engines. Backend (#[Sinful]) and frontend (<!-- @sin -->) run the same flow:
 * detect exactly marked sins, ≥3 diverse scenarios. Subclass supplies its Fixture; the package proves its catalog.
 */
abstract class FixtureTestCase extends TestCase
{
    /**
     * How many files each of a {@see ChainDetector}'s fixture findings must cross — a FIXTURE-proof
     * bar (prove the engine on a deep chain), NOT a detection threshold: a chain detector flags a
     * one-hop phantom just as readily. It lives here, in the test, for exactly that reason.
     */
    private const int MIN_CHAIN_FILES = 5;

    /**
     * The cross-file floor for a {@see \JesseGall\CodeCommandments\Detectors\RecurrenceDetector}: at least
     * one of its recurring `#[Sinful]` groups must span TWO files, proving the fingerprint buckets across
     * classes and not merely within one.
     */
    private const int MIN_RECURRENCE_FILES = 2;

    abstract protected function fixture(): Fixture;

    public function test_detectors_flag_exactly_their_marked_sins(): void
    {
        $results = $this->fixture()->markerResults();

        $this->assertNotEmpty($results, 'no detectors were verified against the fixture');

        foreach ($results as $result) {
            $this->assertSame([], $result->missed, "{$result->detector} missed marked sins");
            $this->assertSame([], $result->unexpected, "{$result->detector} flagged unmarked code (a false positive, or an unmarked sin)");
        }
    }

    public function test_each_detector_fires_on_three_diverse_scenarios(): void
    {
        $diversity = new Diversity();

        // A chain detector's scenario IS its provenance path (see ClassScenarioResolver), so the same
        // overlap rule proves what matters for it: ≥3 findings whose chains take different routes of
        // different kinds — not three shallow lookalikes.
        foreach ($this->fixture()->scenarios() as $detector => $scenarios) {
            $largest = $diversity->largestGroup($scenarios);

            $this->assertGreaterThanOrEqual(
                Diversity::MIN_SCENARIOS,
                $largest,
                sprintf(
                    '%s: needs ≥%d mutually-DIVERSE scenarios (different files, <%d%% overlap) but the largest diverse group of its %d finding(s) is %d. Add genuinely different cases, not copies.',
                    $detector,
                    Diversity::MIN_SCENARIOS,
                    Diversity::MAX_SIMILARITY,
                    count($scenarios),
                    $largest,
                ),
            );
        }
    }

    public function test_recurrence_detectors_prove_a_cross_file_group(): void
    {
        // A recurrence detector buckets the SAME shape across the whole program, so its fixture must prove
        // that cross-class reach: at least one #[Sinful] group spans TWO files. Two copies inside a single
        // class would pass without ever exercising the cross-file bucketing — the thing most likely to
        // regress silently. (Extra same-class groups are welcome; one cross-file group is the floor.)
        $spans = $this->fixture()->recurrenceSpans();
        $this->assertIsArray($spans, 'recurrenceSpans() must map each RecurrenceDetector to its widest group span');

        foreach ($spans as $detector => $widest) {
            $this->assertGreaterThanOrEqual(
                self::MIN_RECURRENCE_FILES,
                $widest,
                "{$detector} is a RecurrenceDetector but its widest #[Sinful] group touches only {$widest} "
                . 'file(s); mark one recurring group ACROSS two classes/files, not just twice in one class.',
            );
        }
    }

    public function test_chain_detectors_prove_a_deep_chain(): void
    {
        // A chain detector must PROVE it can follow a value far — its DEEPEST fixture finding crosses
        // ≥ MIN_CHAIN_FILES. (Shallower findings are fine and expected: a deep field-write chain
        // legitimately also flags its own shorter intermediate hops.)
        $spans = $this->fixture()->chainSpans();
        $this->assertIsArray($spans, 'chainSpans() must map each ChainDetector to its findings\' depths');

        foreach ($spans as $detector => $depths) {
            $this->assertGreaterThanOrEqual(
                self::MIN_CHAIN_FILES,
                $depths === [] ? 0 : max($depths),
                "{$detector} is a ChainDetector but its deepest finding crosses only "
                . ($depths === [] ? 0 : max($depths)) . " file(s); it must follow a value through ≥"
                . self::MIN_CHAIN_FILES . " — prove the chain goes DEEP, not one hop.",
            );
        }
    }
}
