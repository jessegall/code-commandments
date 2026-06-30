<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Testing\FixtureVerifier;
use JesseGall\CodeCommandments\Tests\Support\FixtureTestCase;

/**
 * The backend self-checking fixture: EVERY detector in the {@see Catalog} is run
 * over the whole Shop fixture and checked against its `#[Sinful]` markers. Only the
 * engine-specific hooks live here — the flow and assertions are in {@see FixtureTestCase}.
 */
final class FixtureDetectorTest extends FixtureTestCase
{
    protected function markerResults(): array
    {
        return new FixtureVerifier()->verify($this->fixture(), Catalog::all());
    }

    protected function scenarios(): array
    {
        $codebase = $this->fixture();
        $scenarios = [];

        foreach (Catalog::all() as $detector) {
            $scenarios[$detector::class] = array_map(
                fn (NodeMatch $match): array => ['file' => $match->file->path, 'source' => $this->scopeSource($match)],
                $detector->find($codebase),
            );
        }

        return $scenarios;
    }

    /**
     * The source of the finding's enclosing class (the whole "scenario" — fields,
     * surrounding methods, intent), or its function / the node itself at file scope.
     */
    private function scopeSource(NodeMatch $finding): string
    {
        $scope = $finding->enclosingClass() ?? $finding->enclosingFunction() ?? $finding->node;
        $lines = file($finding->file->path) ?: [];

        return implode('', array_slice($lines, $scope->getStartLine() - 1, $scope->getEndLine() - $scope->getStartLine() + 1));
    }

    private function fixture(): Codebase
    {
        return Codebase::scan(__DIR__ . '/../Fixtures/shop');
    }
}
