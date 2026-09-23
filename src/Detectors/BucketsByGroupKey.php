<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use Closure;
use JesseGall\CodeCommandments\Codebase;
use JesseGall\CodeCommandments\Located;

/**
 * The bucketing every {@see RecurrenceDetector} does, on either engine: key each candidate by its
 * {@see RecurrenceDetector::groupKey}, drop the uncountable, and keep the buckets that recur. Stated
 * over the base {@see Located}/{@see Codebase} so a PHP rule and a TypeScript rule count the same way.
 */
trait BucketsByGroupKey
{
    abstract public function groupKey(Located $finding, Codebase $codebase): ?string;

    /**
     * Every bucket of $candidates holding at least $minimum of them, in the order they were first seen.
     *
     * @template T of Located
     * @param  list<T>  $candidates
     * @return list<list<T>>
     */
    private function recurringBuckets(array $candidates, Codebase $codebase, int $minimum = 2): array
    {
        $buckets = [];

        foreach ($candidates as $candidate) {
            $key = $this->groupKey($candidate, $codebase);

            if ($key !== null) {
                $buckets[$key][] = $candidate;
            }
        }

        return array_values(array_filter($buckets, static fn (array $occurrences) => count($occurrences) >= $minimum));
    }

    /**
     * The members of $candidates' recurring buckets that have no byte-identical twin among them — the
     * near copies. A member with an exact twin is the exact-duplicate rule's finding, so the two never
     * report the same line.
     *
     * @template T of Located
     *
     * @param  list<T>  $candidates
     * @param  Closure(T): string  $exact  the fingerprint two byte-identical members share
     * @return list<T>
     */
    private function nearCopies(array $candidates, Codebase $codebase, Closure $exact): array
    {
        $copies = array_count_values(array_map($exact, $candidates));
        $near = [];

        foreach ($this->recurringBuckets($candidates, $codebase) as $bucket) {
            foreach ($bucket as $candidate) {
                if ($copies[$exact($candidate)] === 1) {
                    $near[] = $candidate;
                }
            }
        }

        return $near;
    }
}
