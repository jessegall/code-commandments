<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use Attribute;

/**
 * Marks a fixture declaration as containing a sin a given detector must flag.
 * Repeatable — stack one per sin. `$detector` is the detector identifier (its
 * skill slug, or `Detector::class`); `$line` is optional extra precision.
 *
 * The fixture is parsed, never loaded, so this attribute is read off the AST by
 * {@see SinMarkers} — the detector tests cross-check their findings against it.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Sinful
{
    public function __construct(
        public readonly string $detector,
        public readonly ?int $line = null,
    ) {}
}
