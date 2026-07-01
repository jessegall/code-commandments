<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Testing\Marker;
use JesseGall\CodeCommandments\Testing\SinMarkers;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Sfc;
use PHPUnit\Framework\TestCase;

/**
 * The `#[Righteous]` contract — two guarantees, both over the backend fixture:
 *
 *  1. **Every sin has at least one righteous twin.** Unlike `#[Sinful]` (≥3 diverse),
 *     there is no minimum-diversity or upper bound — just one clean example per sin, so
 *     the generated `SKILL.md` always has a "good" half to show.
 *  2. **No detector ever flags righteous code.** The good example a skill teaches must
 *     itself be clean — a detector flagging a `#[Righteous]` declaration would mean the
 *     skill is publishing a "fix" that is itself a sin (a false positive in the docs).
 */
final class RighteousNeverFlaggedTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../Fixtures/backend';

    private const string FRONTEND = __DIR__ . '/../Fixtures/frontend';

    public function test_every_detector_has_at_least_one_righteous_twin(): void
    {
        $codebase = Codebase::scan(self::FIXTURE);
        $righteous = SinMarkers::in($codebase, 'Righteous');

        foreach (Catalog::backend() as $detector) {
            $this->assertNotSame(
                [],
                $this->forDetector($righteous, $detector),
                $this->shortName($detector) . " has no #[Righteous] twin — add one good example for its sin.",
            );
        }
    }

    public function test_no_detector_flags_righteous_code(): void
    {
        $codebase = Codebase::scan(self::FIXTURE);
        $righteous = SinMarkers::in($codebase, 'Righteous');

        $violations = [];

        foreach (Catalog::backend() as $detector) {
            foreach ($detector->find($codebase) as $finding) {
                foreach ($righteous as $marker) {
                    if ($marker->covers($finding->enclosingClassName() ?? '(file)', $finding->enclosingFunctionName())) {
                        $violations[] = $this->shortName($detector) . ' flagged righteous code at ' . $finding->location();
                    }
                }
            }
        }

        $this->assertSame([], $violations, "A detector flagged #[Righteous] code — the skill's good example is itself a sin:\n" . implode("\n", $violations));
    }

    public function test_every_frontend_detector_has_at_least_one_righteous_twin(): void
    {
        $righteous = $this->frontendRighteous();

        foreach (Catalog::frontend() as $detector) {
            $name = (new \ReflectionClass($detector->sin()))->getShortName();
            $this->assertNotSame([], $righteous[$name] ?? [], "{$name} has no <!-- @righteous --> twin — add one good example for its sin.");
        }
    }

    public function test_no_frontend_detector_flags_righteous_markup(): void
    {
        $codebase = VueCodebase::scan(self::FRONTEND);
        $righteous = $this->frontendRighteous();
        $violations = [];

        foreach (Catalog::frontend() as $detector) {
            $name = (new \ReflectionClass($detector->sin()))->getShortName();
            $marked = $righteous[$name] ?? [];

            foreach ($detector->find($codebase) as $finding) {
                if (in_array($finding->location(), $marked, true)) {
                    $violations[] = (new \ReflectionClass($detector))->getShortName() . ' flagged righteous markup at ' . $finding->location();
                }
            }
        }

        $this->assertSame([], $violations, "A frontend detector flagged <!-- @righteous --> markup:\n" . implode("\n", $violations));
    }

    /**
     * The `file:line` of every `<!-- @righteous Name -->`-marked element, grouped by Name.
     *
     * @return array<string, list<string>>
     */
    private function frontendRighteous(): array
    {
        $marked = [];

        foreach (VueCodebase::scan(self::FRONTEND)->components() as $component) {
            $this->collectRighteous($component->template, $component, $marked);
        }

        return $marked;
    }

    /**
     * @param  array<string, list<string>>  $marked
     */
    private function collectRighteous(Element $node, Sfc $component, array &$marked): void
    {
        $pending = [];

        foreach ($node->children as $child) {
            if ($child->isComment()) {
                if (preg_match('/@righteous\s+(\w+)/', $child->text, $m) === 1) {
                    $pending[] = $m[1];
                }

                continue;
            }

            if ($child->isElement()) {
                foreach ($pending as $name) {
                    $marked[$name][] = $component->path . ':' . $child->line;
                }

                $pending = [];
            }

            $this->collectRighteous($child, $component, $marked);
        }
    }

    /**
     * @param  list<Marker>  $markers
     * @return list<Marker>
     */
    private function forDetector(array $markers, Detector $detector): array
    {
        $names = [$detector::class, $detector->sin()::class];

        return array_values(array_filter($markers, static fn (Marker $m): bool => in_array($m->detector, $names, true)));
    }

    private function shortName(Detector $detector): string
    {
        return (new \ReflectionClass($detector))->getShortName();
    }
}
