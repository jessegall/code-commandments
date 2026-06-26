<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Testing\FixtureVerifier;
use JesseGall\CodeCommandments\Testing\Marker;
use JesseGall\CodeCommandments\Testing\SinMarkers;
use PHPUnit\Framework\TestCase;

/**
 * The integration layer: EVERY detector in the {@see Catalog} is run over the
 * whole Shop fixture and checked against its `#[Sinful]` markers. Detectors are
 * discovered, not listed — adding a detector file enrolls it here automatically,
 * so a new detector can never silently skip the fixture check.
 */
final class FixtureDetectorTest extends TestCase
{
    public function test_detectors_match_the_fixture_markers(): void
    {
        $codebase = $this->fixture();

        foreach (new FixtureVerifier()->verify($codebase, Catalog::all()) as $result) {
            $this->assertSame([], $result->missed, "{$result->detector} missed marked sins");
            $this->assertSame([], $result->unexpected, "{$result->detector} flagged unmarked code (a false positive, or an unmarked #[Sinful])");
        }
    }

    /**
     * A detector with no `#[Sinful]` in the fixture would pass vacuously — it
     * flags nothing, so it has no missed and no unexpected. This guards the gap:
     * every shipped detector must have at least one marked sin proving it fires.
     */
    public function test_every_detector_has_at_least_one_fixture_marker(): void
    {
        $marked = array_unique(array_map(
            static fn (Marker $m): string => $m->detector,
            SinMarkers::in($this->fixture()),
        ));

        foreach (Catalog::all() as $detector) {
            $id = $detector::class;

            $this->assertContains(
                $id,
                $marked,
                "{$id} has no #[Sinful(...)] marker in the Shop fixture — add a sinful example so the detector is proven to fire (and a righteous twin it must NOT flag).",
            );
        }
    }

    private function fixture(): Codebase
    {
        return Codebase::scan(__DIR__ . '/../Fixtures/shop');
    }
}
