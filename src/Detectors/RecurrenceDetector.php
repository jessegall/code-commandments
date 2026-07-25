<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Codebase;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Located;

/**
 * A detector whose verdict is a RECURRENCE — one occurrence proves nothing; the sin is the SAME shape
 * appearing in ≥2 places. It buckets candidates by a {@see groupKey} fingerprint and flags every member of
 * a bucket seen enough times. It IS a {@see Detector} (implement THIS instead of an engine's `Detector`,
 * not alongside it), and tagging earns a fixture contract fit for a cross-site rule: at least one of its
 * marked groups must span TWO files, so the fixture proves the fingerprint buckets ACROSS files — not
 * merely within one. {@see \JesseGall\CodeCommandments\Testing\FixtureTestCase} reads that from
 * {@see groupKey}.
 *
 * Engine-agnostic on purpose: recurrence is a SHAPE of rule, not a parse strategy, so it is stated over
 * the base {@see Located}/{@see Codebase} types and both engines answer it — a repeated PHP guard and a
 * repeated Vue block are the same kind of sin, and the fixture harness holds both to the same proof. Each
 * engine narrows at ONE seam: backend detectors extend
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\RecurringPattern}, which does the narrowing once and
 * asks its subclasses only for a fingerprint.
 *
 * @see \JesseGall\CodeCommandments\Detectors\Backend\RepeatedGuardDetector
 * @see \JesseGall\CodeCommandments\Detectors\Frontend\DuplicateElementDetector
 */
interface RecurrenceDetector extends Detector
{
    /**
     * The bucket $finding belongs to — the canonical fingerprint of its recurring shape, so two occurrences
     * that ARE the same sin share a key and different shapes never collide. Null when $finding is not a
     * countable candidate (a non-recurring shape the detector skips, or a finding from the other engine).
     */
    public function groupKey(Located $finding, Codebase $codebase): ?string;
}
