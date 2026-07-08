<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;

/**
 * The shared machinery of every {@see RecurrenceDetector}: gather candidates, bucket them by their
 * {@see RecurrenceDetector::groupKey} fingerprint, and flag every member of a bucket seen at least
 * {@see minimumOccurrences} times. A concrete recurrence detector supplies only the two things that
 * genuinely differ — which nodes are candidates, and how a candidate fingerprints — never the loop.
 */
abstract class RecurringPattern implements RecurrenceDetector
{
    /**
     * The nodes to bucket — the shapes this detector counts. {@see groupKey} decides which of them are
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

    final public function find(Codebase $codebase): array
    {
        $buckets = [];

        foreach ($this->candidates($codebase) as $candidate) {
            $key = $this->groupKey($candidate, $codebase);

            if ($key !== null) {
                $buckets[$key][] = $candidate;
            }
        }

        $findings = [];

        foreach ($buckets as $occurrences) {
            if (count($occurrences) >= $this->minimumOccurrences()) {
                array_push($findings, ...$occurrences);
            }
        }

        return $findings;
    }
}
