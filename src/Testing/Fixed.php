<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use Attribute;

/**
 * Marks a fixture declaration as the RESOLUTION of a sin — the `#[Sinful]` code repaired the way
 * that sin's rule says — which is what {@see FixtureExamples} publishes as the "good" half, in
 * preference to {@see Righteous}. The two are different claims: righteous is about the DETECTOR (a
 * look-alike it must not flag, usually an exemption), fixed is about the FIX (what the bad code
 * should become). A fix the detector still flags is not one, so this implies righteous but never
 * the reverse. Repeatable; `$detector` (class or slug) matches {@see Sinful}.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Fixed
{
    public function __construct(
        public readonly string $detector,
        public readonly ?int $line = null,
    ) {}
}
