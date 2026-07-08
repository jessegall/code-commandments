<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * A work-in-progress detector (and its sin) that is NOT yet published — built and unit-tested in the repo,
 * but deliberately excluded from the discovered catalogs, so it never runs in `judge`, the fixture verifier,
 * the generated docs, or a release while it is still being calibrated. Instantiate it directly in tests and
 * a calibration probe; when it proves clean on real codebases, drop this marker to enrol it.
 *
 * A marker, not a config flag: the exclusion lives with the class, so there is no second list to forget.
 * {@see \JesseGall\CodeCommandments\Detectors\Catalog} and {@see \JesseGall\CodeCommandments\Sins\Catalog}
 * both skip anything implementing it.
 */
interface Unpublished {}
