<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A detector whose verdict is a RECURRENCE — one occurrence proves nothing; the sin is the SAME shape
 * appearing in ≥2 places. It buckets candidates by a {@see groupKey} fingerprint and flags every member of
 * a bucket seen enough times. It IS a {@see Detector} (implement THIS instead of `Detector`, not alongside
 * it), and tagging earns a fixture contract fit for a cross-site rule: at least one of its `#[Sinful]`
 * groups must span TWO files, so the fixture proves the fingerprint buckets ACROSS classes — not merely
 * within one. {@see \JesseGall\CodeCommandments\Testing\FixtureTestCase} reads that from {@see groupKey}.
 *
 * The shared "bucket by key, keep ≥ threshold, flatten" loop lives once on
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\RecurringPattern} — extend that base rather than
 * re-implementing `find`.
 *
 * @see \JesseGall\CodeCommandments\Detectors\Backend\RepeatedGuardDetector
 */
interface RecurrenceDetector extends Detector
{
    /**
     * The bucket $finding belongs to — the canonical fingerprint of its recurring shape, so two occurrences
     * that ARE the same sin share a key and different shapes never collide. Null when $finding is not a
     * countable candidate (a non-recurring shape the detector skips).
     */
    public function groupKey(NodeMatch $finding, Codebase $codebase): ?string;
}
