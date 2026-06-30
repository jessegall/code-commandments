<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Testing\Diversity;
use JesseGall\CodeCommandments\Testing\DetectorResult;
use PHPUnit\Framework\TestCase;

/**
 * The ONE self-checking-fixture test, shared by every engine. Both the backend
 * (`#[Sinful]` markers) and frontend (`<!-- @sin -->` comments) fixtures are the
 * same flow — every detector must flag exactly its marked sins and fire on at least
 * three mutually-diverse scenarios — so the flow and its assertions live here once.
 *
 * A subclass supplies only what differs between engines: the per-detector marker
 * verification, and each detector's findings as diversity scenarios. The diversity
 * engine ({@see Diversity}) and the result shape ({@see DetectorResult}) are shared.
 */
abstract class FixtureTestCase extends TestCase
{
    /**
     * Run every detector over the fixture and check it against its markers.
     *
     * @return list<DetectorResult>
     */
    abstract protected function markerResults(): array;

    /**
     * Each detector's findings reduced to diversity scenarios.
     *
     * @return array<string, list<array{file: string, source: string}>>  detector => scenarios
     */
    abstract protected function scenarios(): array;

    public function test_detectors_flag_exactly_their_marked_sins(): void
    {
        $results = $this->markerResults();

        $this->assertNotEmpty($results, 'no detectors were verified against the fixture');

        foreach ($results as $result) {
            $this->assertSame([], $result->missed, "{$result->detector} missed marked sins");
            $this->assertSame([], $result->unexpected, "{$result->detector} flagged unmarked code (a false positive, or an unmarked sin)");
        }
    }

    public function test_each_detector_fires_on_three_diverse_scenarios(): void
    {
        $diversity = new Diversity();

        foreach ($this->scenarios() as $detector => $scenarios) {
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
}
