<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Support\ClassName;
use JesseGall\CodeCommandments\Testing\Marker;
use JesseGall\CodeCommandments\Testing\SinMarkers;
use PHPUnit\Framework\TestCase;

/**
 * The `#[Fixed]` contract — a fixture declaration marked as a sin's RESOLUTION is the bad code
 * repaired the way that sin's rule says, and it is what the generated skill publishes as "Good".
 *
 * The guarantee here is the one that makes a resolution worth publishing: it must not itself be
 * the sin. A "fix" the detector still flags is not a fix, and shipping one would teach a reader
 * to make the same mistake twice.
 *
 * Coverage — that EVERY sin carries a resolution rather than falling back to its `#[Righteous]`
 * look-alike — is enforced separately, once the canon is filled in.
 */
final class FixedIsTheResolutionTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../Fixtures/backend';

    public function test_no_detector_flags_the_code_marked_as_its_own_fix(): void
    {
        $codebase = Codebase::scan(self::FIXTURE);
        $fixed = SinMarkers::in($codebase, 'Fixed');

        $violations = [];

        foreach (Catalog::backend() as $detector) {
            foreach ($detector->find($codebase) as $finding) {
                foreach ($this->forDetector($fixed, $detector) as $marker) {
                    if ($marker->covers($finding->enclosingClassName() ?? '(file)', $finding->enclosingFunctionName())) {
                        $violations[] = $this->shortName($detector) . ' flagged its own #[Fixed] resolution at ' . $finding->location();
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "A detector flagged code marked as the fix for its own sin — the published 'good' example still IS the sin:\n"
            . implode("\n", $violations),
        );
    }

    public function test_a_resolution_names_a_sin_the_canon_knows(): void
    {
        $codebase = Codebase::scan(self::FIXTURE);
        $known = [];

        foreach (Catalog::backend() as $detector) {
            $known[$detector->sin()::class] = true;
            $known[$detector::class] = true;
            $known[$detector->sin()->name()] = true;
        }

        foreach (SinMarkers::in($codebase, 'Fixed') as $marker) {
            $this->assertArrayHasKey(
                $marker->detector,
                $known,
                "#[Fixed({$marker->detector})] at {$marker->location} names nothing in the catalog — a typo here silently"
                . ' drops the resolution and the docs fall back to the righteous look-alike.',
            );
        }
    }

    /**
     * @param  list<Marker>  $markers
     * @return list<Marker>
     */
    private function forDetector(array $markers, Detector $detector): array
    {
        $keys = [$detector->sin()::class, $detector::class, $detector->sin()->name()];

        return array_values(array_filter($markers, static fn (Marker $marker): bool => in_array($marker->detector, $keys, true)));
    }

    private function shortName(Detector $detector): string
    {
        return ClassName::short($detector::class);
    }
}
