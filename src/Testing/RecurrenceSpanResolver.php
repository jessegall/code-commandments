<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Codebase;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;

/**
 * For each {@see RecurrenceDetector} in the set, the widest FILE span of any of its marked groups — how
 * many distinct files the largest recurrence bucket touches. {@see FixtureTestCase} asserts it reaches
 * two, so a fingerprint is proven to bucket ACROSS files: a guard copied into two classes, a Vue block
 * repeated in two components. Engine-agnostic like the contract it checks — it reads a finding's file
 * through {@see Located} — so both fixtures share this one resolver.
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
                if ($finding instanceof Located) {
                    $buckets[$detector->groupKey($finding, $codebase)][$finding->file()] = true;
                }
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
