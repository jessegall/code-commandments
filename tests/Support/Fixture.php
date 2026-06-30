<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Testing\DetectorResult;

/**
 * One engine's self-checking fixture, reduced to the two things {@see FixtureTestCase}
 * needs: the per-detector marker check and the diversity scenarios. Each engine's
 * implementation ({@see BackendFixture}, {@see FrontendFixture}) just composes its
 * {@see \JesseGall\CodeCommandments\Testing\MarkerVerifier} and
 * {@see \JesseGall\CodeCommandments\Testing\ScenarioResolver} — no test-side logic.
 */
interface Fixture
{
    public function name(): string;

    /**
     * @return list<DetectorResult>
     */
    public function markerResults(): array;

    /**
     * @return array<string, list<array{file: string, source: string}>>
     */
    public function scenarios(): array;
}
