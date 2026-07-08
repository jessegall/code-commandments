<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;

/**
 * For each {@see RecurrenceDetector} in the set, the widest FILE span of any of its `#[Sinful]` groups —
 * how many distinct files the largest single recurrence bucket touches. {@see FixtureTestCase} asserts it
 * reaches two, so every recurrence detector proves at least once that its fingerprint buckets ACROSS
 * classes: a copied guard in two files is caught, not just two copies inside one class.
 */
final class RecurrenceSpanResolver
{
    /**
     * @param  list<object>  $detectors
     * @return array<string, int>  recurrence-detector class => widest cross-file span of one group
     */
    public function resolve(Codebase $codebase, array $detectors): array
    {
        $spans = [];

        foreach ($detectors as $detector) {
            if (! $detector instanceof RecurrenceDetector) {
                continue;
            }

            $buckets = [];

            foreach ($detector->find($codebase) as $finding) {
                $buckets[$detector->groupKey($finding, $codebase)][$finding->file->path] = true;
            }

            $spans[$detector::class] = array_reduce(
                $buckets,
                static fn (int $widest, array $files): int => max($widest, count($files)),
                0,
            );
        }

        return $spans;
    }
}
