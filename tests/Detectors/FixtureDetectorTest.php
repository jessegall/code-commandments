<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Testing\FixtureVerifier;
use PHPUnit\Framework\TestCase;

/**
 * The integration layer: EVERY detector in the {@see Catalog} is run over the
 * whole Shop fixture and checked against its `#[Sinful]` markers. Detectors are
 * discovered, not listed — adding a detector file enrolls it here automatically,
 * so a new detector can never silently skip the fixture check.
 */
final class FixtureDetectorTest extends TestCase
{
    private const int MIN_TRIGGERS = 3;

    /** Two findings whose enclosing code overlaps this much are the SAME scenario. */
    private const int MAX_SIMILARITY = 60;

    public function test_detectors_match_the_fixture_markers(): void
    {
        $codebase = $this->fixture();

        foreach (new FixtureVerifier()->verify($codebase, Catalog::all()) as $result) {
            $this->assertSame([], $result->missed, "{$result->detector} missed marked sins");
            $this->assertSame([], $result->unexpected, "{$result->detector} flagged unmarked code (a false positive, or an unmarked #[Sinful])");
        }
    }

    /**
     * The coverage floor: every detector must fire on at least three DIVERSE
     * sinful scenarios. "Diverse" means pairwise in different files AND with less
     * than 60% code overlap in their enclosing method/class — so the same pattern
     * pasted twice (even across files or namespaces) counts once, not twice. This
     * forces real breadth: different call shapes, different surroundings, proving
     * the detector generalises instead of matching one demo.
     */
    public function test_every_detector_fires_on_three_diverse_scenarios(): void
    {
        $codebase = $this->fixture();

        foreach (Catalog::all() as $detector) {
            $findings = $detector->find($codebase);
            $max = $this->largestDiverseGroup($findings);

            $this->assertGreaterThanOrEqual(
                self::MIN_TRIGGERS,
                $max,
                sprintf(
                    '%s / %s has %d finding(s) [%s] but its largest mutually-DIVERSE group is %d — needs %d, each in a different file with <%d%% class overlap. Add genuinely different cases, not copies of the same pattern.',
                    $detector->skill(),
                    $detector::class,
                    count($findings),
                    implode(', ', array_map(static fn (NodeMatch $m): string => basename($m->file->path) . ':' . $m->line(), $findings)),
                    $max,
                    self::MIN_TRIGGERS,
                    self::MAX_SIMILARITY,
                ),
            );
        }
    }

    /**
     * The size of the largest group of findings that are ALL pairwise diverse
     * (different files, <60% class overlap) — a max-clique over the diversity
     * graph. Order-independent: a single finding that resembles everything can't
     * mask a genuinely diverse trio hiding behind it.
     *
     * @param  list<NodeMatch>  $findings
     */
    private function largestDiverseGroup(array $findings): int
    {
        $n = count($findings);
        $diverse = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $diverse[$i][$j] = $diverse[$j][$i] = $findings[$i]->file->path !== $findings[$j]->file->path
                    && $this->similarity($this->scopeSource($findings[$i]), $this->scopeSource($findings[$j])) < self::MAX_SIMILARITY;
            }
        }

        return $this->growClique(range(0, $n - 1), 0, $diverse);
    }

    /**
     * @param  list<int>  $candidates  node indices that may still extend the clique
     * @param  array<int, array<int, bool>>  $diverse
     */
    private function growClique(array $candidates, int $size, array $diverse): int
    {
        $best = $size;

        foreach ($candidates as $k => $node) {
            $rest = array_values(array_filter(
                array_slice($candidates, $k + 1),
                static fn (int $other): bool => $diverse[$node][$other] ?? false,
            ));

            $best = max($best, $this->growClique($rest, $size + 1, $diverse));
        }

        return $best;
    }

    /**
     * The source of the finding's enclosing class (the whole "scenario" — fields,
     * surrounding methods, intent), or its function / the node itself at file
     * scope. Class-level so two findings in the same class compare as identical
     * (one scenario) and copy-pasted classes across files collapse too.
     */
    private function scopeSource(NodeMatch $finding): string
    {
        $scope = $finding->enclosingClass() ?? $finding->enclosingFunction() ?? $finding->node;
        $lines = file($finding->file->path) ?: [];

        return implode('', array_slice($lines, $scope->getStartLine() - 1, $scope->getEndLine() - $scope->getStartLine() + 1));
    }

    private function similarity(string $a, string $b): float
    {
        similar_text($this->normalise($a), $this->normalise($b), $percent);

        return $percent;
    }

    private function normalise(string $code): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($code));
    }

    private function fixture(): Codebase
    {
        return Codebase::scan(__DIR__ . '/../Fixtures/shop');
    }
}
