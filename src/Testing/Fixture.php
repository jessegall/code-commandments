<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * One self-checking fixture, reduced to the two things {@see FixtureTestCase} needs:
 * the per-detector marker check and the diversity scenarios. Each engine's
 * implementation ({@see BackendFixture}, {@see FrontendFixture}) composes its
 * {@see MarkerVerifier} and {@see ScenarioResolver} over a scanned codebase — no
 * test-side logic. {@see DeclaredFixture} fans a mixed detector set out across the
 * paths each detector declares via {@see HasFixture}.
 */
interface Fixture
{
    /**
     * @return list<DetectorResult>
     */
    public function markerResults(): array;

    /**
     * @return array<string, list<array{file: string, source: string}>>
     */
    public function scenarios(): array;

    /**
     * Per {@see \JesseGall\CodeCommandments\Detectors\ChainDetector}, the file-depth of each of its
     * findings — held against {@see \JesseGall\CodeCommandments\Detectors\ChainDetector::MIN_CHAIN_FILES}.
     *
     * @return array<string, list<int>>
     */
    public function chainSpans(): array;
}
