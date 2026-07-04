<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Detectors\ChainDetector;
use PHPUnit\Framework\TestCase;

/**
 * The self-checking-fixture test, shared by every engine. Both the backend
 * (`#[Sinful]` markers) and frontend (`<!-- @sin -->` comments) run the same flow —
 * every detector must flag exactly its marked sins and fire on at least three
 * mutually-diverse scenarios — so the flow and its assertions live here once.
 *
 * A subclass supplies only its {@see Fixture}. To prove your OWN detectors, return a
 * {@see DeclaredFixture} over them and let each declare its directory via
 * {@see HasFixture}; the package proves its own catalog with a {@see BackendFixture}
 * / {@see FrontendFixture} pointed at the shared Shop app.
 */
abstract class FixtureTestCase extends TestCase
{
    /**
     * How many files each of a {@see ChainDetector}'s fixture findings must cross — a FIXTURE-proof
     * bar (prove the engine on a deep chain), NOT a detection threshold: a chain detector flags a
     * one-hop phantom just as readily. It lives here, in the test, for exactly that reason.
     */
    private const int MIN_CHAIN_FILES = 5;

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

    public function test_chain_detectors_cross_enough_files_per_finding(): void
    {
        $shallow = [];

        foreach ($this->fixture()->chainSpans() as $detector => $depths) {
            foreach ($depths as $depth) {
                if ($depth < self::MIN_CHAIN_FILES) {
                    $shallow[] = "{$detector}: a finding crosses only {$depth} file(s)";
                }
            }
        }

        $this->assertSame(
            [],
            $shallow,
            "Every ChainDetector finding must follow a value through ≥" . self::MIN_CHAIN_FILES
            . " files — prove the chain goes DEEP, not one hop:\n" . implode("\n", $shallow),
        );
    }
}
