<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\ChainDetector;

use JesseGall\CodeCommandments\Ast\NodeMatch;

/**
 * For each {@see ChainDetector} in the set, the file-depth of EVERY fixture finding — how many
 * distinct files that finding's deciding chain crosses. {@see FixtureTestCase} asserts each reaches
 * {@see ChainDetector::MIN_CHAIN_FILES}, so every one of a chain detector's samples is proven on a
 * genuinely deep, multi-file chain (their diversity — different routes and kinds — is the scenario
 * test's job, driven by the same {@see ChainDetector::chainPath}).
 */
final class ChainSpanResolver
{
    /**
     * @param  list<object>  $detectors
     * @return array<string, list<int>>  chain-detector class => each finding's file span
     */
    public function resolve(Codebase $codebase, array $detectors): array
    {
        $spans = [];

        foreach ($detectors as $detector) {
            if (! $detector instanceof ChainDetector) {
                continue;
            }

            $spans[$detector::class] = array_map(
                static fn (NodeMatch $finding): int => count(array_unique(
                    array_map(static fn (string $step): string => explode('@', $step, 2)[1], $detector->chainPath($finding, $codebase)),
                )),
                $detector->find($codebase),
            );
        }

        return $spans;
    }
}
