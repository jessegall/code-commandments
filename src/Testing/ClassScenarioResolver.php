<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\ChainDetector;

/**
 * The backend {@see ScenarioResolver}: a finding's scenario is its enclosing CLASS — the whole
 * surrounding intent (fields, sibling methods) — so two findings in one class compare as identical
 * and copy-pasted classes collapse. For a {@see ChainDetector}, the scenario is instead its PROVENANCE
 * PATH (the `edgeKind@file` steps): what proves a chain detector diverse is that its findings travel
 * different routes of different kinds, not that their flagged classes look different.
 */
final class ClassScenarioResolver implements ScenarioResolver
{
    /**
     * @param  Codebase  $codebase
     * @param  list<Detector>  $detectors
     * @return array<string, list<array{file: string, source: string}>>
     */
    public function resolve(BaseCodebase $codebase, array $detectors): array
    {
        $scenarios = [];

        foreach ($detectors as $detector) {
            $scenarios[$detector::class] = array_map(
                fn (NodeMatch $match) => [
                    'file' => $match->file->path,
                    'source' => $detector instanceof ChainDetector
                        ? implode("\n", $detector->chainPath($match, $codebase))
                        : $this->scopeSource($match),
                ],
                $detector->find($codebase),
            );
        }

        return $scenarios;
    }

    private function scopeSource(NodeMatch $finding): string
    {
        $scope = $finding->enclosingClass() ?? $finding->enclosingFunction() ?? $finding->node;
        $lines = file($finding->file->path) ?: [];

        return implode('', array_slice($lines, $scope->getStartLine() - 1, $scope->getEndLine() - $scope->getStartLine() + 1));
    }
}
