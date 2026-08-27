<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Support\ClassName;
use JesseGall\CodeCommandments\Testing\FixtureExamples;
use JesseGall\CodeCommandments\Testing\Marker;
use JesseGall\CodeCommandments\Testing\DeclarationMarkers;
use JesseGall\CodeCommandments\Testing\SinMarkers;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use PHPUnit\Framework\TestCase;

/**
 * The `#[Fixed]` contract — a fixture declaration marked as a sin's RESOLUTION is the bad code
 * repaired the way that sin's rule says, and it is what the generated skill publishes as "Good".
 *
 * The guarantee here is the one that makes a resolution worth publishing: it must not itself be
 * the sin. A "fix" the detector still flags is not a fix, and shipping one would teach a reader
 * to make the same mistake twice.
 *
 * Coverage is enforced here too: every sin must carry one. A sin without a resolution falls back to
 * its `#[Righteous]` look-alike, which is usually a documented EXEMPTION — so the skill quietly
 * teaches the escape hatch instead of the fix, and nothing fails to say so.
 */
final class FixedIsTheResolutionTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../Fixtures/backend';

    private const string FRONTEND = __DIR__ . '/../Fixtures/frontend';

    public function test_every_sin_carries_a_resolution(): void
    {
        $missing = FixtureExamples::withoutResolution(Codebase::scan(self::FIXTURE), Catalog::backend());

        $this->assertSame(
            [],
            array_map(ClassName::short(...), $missing),
            "These sins have no #[Fixed] twin, so their published 'good' example falls back to a\n"
            . "righteous look-alike — code that legitimately DODGES the rule rather than obeying it.\n"
            . 'Add the fix to the fixture; see the `detector-fixtures` skill.',
        );
    }

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

    public function test_every_frontend_sin_carries_a_resolution(): void
    {
        $fixed = DeclarationMarkers::in(VueCodebase::scan(self::FRONTEND), 'fixed');
        $missing = [];

        foreach (Catalog::frontend() as $detector) {
            $name = (new \ReflectionClass($detector->sin()))->getShortName();

            if (($fixed[$name] ?? []) === []) {
                $missing[] = $name;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "These frontend sins have no <!-- @fixed --> twin, so their published 'good' example falls\n"
            . "back to a @righteous look-alike — markup that legitimately DODGES the rule rather than\n"
            . 'obeying it. Add the repair to the .vue fixture; see the `detector-fixtures` skill.',
        );
    }

    /**
     * A file may hold several scenarios for several sins, but not two for the SAME sin: the sinful
     * marker is what anchors a scenario to its file, so a second one there leaves the two repairs
     * with no way to be told apart and the good half publishes both as one fix.
     *
     * A scenario is counted by the CLASS, not the marker. One sin flagged at several sites of one
     * class is a single scenario — that repetition IS the sin for a whole family of rules — and its
     * repair is naturally several declarations. Two CLASSES sinful and two classes repaired in one
     * file is the shape nothing can tell apart.
     */
    public function test_a_file_holds_at_most_one_scenario_per_sin(): void
    {
        $codebase = Codebase::scan(self::FIXTURE);
        $ambiguous = [];

        foreach (Catalog::backend() as $detector) {
            $keys = [$detector->sin()::class, $detector::class, $detector->sin()->name()];

            foreach ($this->filesWithSeveralPairedClasses($codebase, $keys) as $file) {
                $ambiguous[] = $this->shortName($detector) . ' has two scenarios in ' . $file;
            }
        }

        $this->assertSame(
            [],
            $ambiguous,
            "A file holds two sinful declarations AND two resolutions for one sin, so which repair\n"
            . "answers which sin is undecidable — and the published good half merges them. Split the\n"
            . "scenarios into separate fixture files:\n" . implode("\n", $ambiguous),
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
     * The fixture files where more than one class carries BOTH a sinful declaration and a resolution
     * for the given sin — the one shape that cannot be resolved, since the pairing matches a fix to
     * the sin in its own class and there would be two equally good answers.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function filesWithSeveralPairedClasses(Codebase $codebase, array $keys): array
    {
        $marked = [];

        foreach (['Sinful', 'Fixed'] as $attribute) {
            foreach (SinMarkers::in($codebase, $attribute) as $marker) {
                if (in_array($marker->detector, $keys, true)) {
                    $marked[explode(':', $marker->location)[0]][$marker->class][$attribute] = true;
                }
            }
        }

        $ambiguous = [];

        foreach ($marked as $file => $classes) {
            $paired = array_filter($classes, static fn (array $halves): bool => count($halves) === 2);

            if (count($paired) > 1) {
                $ambiguous[] = $file;
            }
        }

        return $ambiguous;
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
