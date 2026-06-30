<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use Attribute;

/**
 * Marks a fixture declaration as the RIGHTEOUS twin of a sin — the good code a given
 * detector must NOT flag, and the "good" half of the skill's bad → good example. The
 * {@see Sinful} twin is the bad half; together they generate the worked example in the
 * detector's skill, sourced from real, tested fixture code (never a hand-written
 * snippet that can rot).
 *
 * Repeatable — stack one per sin a declaration is the clean counterpart of. `$detector`
 * is the detector identifier (its `Detector::class`, or skill slug), matching {@see Sinful}.
 *
 * The fixture is parsed, never loaded, so this is read off the AST by
 * {@see RighteousMarkers}. (Any unmarked clean code is righteous too — this attribute
 * just CATALOGS the canonical good example to show beside the sin.)
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Righteous
{
    public function __construct(
        public readonly string $detector,
        public readonly ?int $line = null,
    ) {}
}
