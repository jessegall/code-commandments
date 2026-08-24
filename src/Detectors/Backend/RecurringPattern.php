<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;

/**
 * The shared machinery of every BACKEND {@see RecurrenceDetector}: gather candidates, bucket them by
 * their {@see fingerprint}, and flag every member of a bucket seen at least {@see minimumOccurrences}
 * times — a concrete detector supplies only which nodes are candidates and how one fingerprints, never
 * the loop. The narrowing to a {@see NodeMatch} it shares with every other backend recurrence rule is
 * {@see GroupsByFingerprint}'s.
 */
abstract class RecurringPattern implements Detector, RecurrenceDetector
{
    use GroupsByFingerprint;

    /**
     * The nodes to bucket — the shapes this detector counts. {@see fingerprint} decides which of them are
     * countable (a null key drops one) and which collide.
     *
     * @return list<NodeMatch>
     */
    abstract protected function candidates(Codebase $codebase): array;

    /**
     * How many occurrences of one fingerprint make it a sin. Two — a shape written twice is already a
     * pattern — unless a detector widens it.
     */
    protected function minimumOccurrences(): int
    {
        return 2;
    }

    /**
     * Is a bucket that MET the count actually the pattern? The second condition a fingerprint cannot
     * carry, because it is about the occurrences as a set rather than about any one of them — "and they
     * must differ somewhere", say. Accepts by default, so a detector whose key says it all ignores this.
     *
     * @param  list<NodeMatch>  $occurrences  every candidate sharing one fingerprint
     */
    protected function qualifies(array $occurrences, Codebase $codebase): bool
    {
        return true;
    }

    final public function find(Codebase $codebase): array
    {
        $buckets = [];

        foreach ($this->candidates($codebase) as $candidate) {
            $key = $this->fingerprint($candidate, $codebase);

            if ($key !== null) {
                $buckets[$key][] = $candidate;
            }
        }

        $findings = [];

        foreach ($buckets as $occurrences) {
            if (count($occurrences) >= $this->minimumOccurrences() && $this->qualifies($occurrences, $codebase)) {
                array_push($findings, ...$occurrences);
            }
        }

        return $findings;
    }
}
