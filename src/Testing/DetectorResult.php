<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * One detector's verdict against the fixture's `#[Sinful]` markers.
 *
 * - `missed`: marked sins the detector failed to flag.
 * - `unexpected`: locations the detector flagged that are NOT marked sinful —
 *   a false positive (fix the detector) or an unmarked sin (mark it).
 */
final class DetectorResult
{
    /**
     * @param  list<string>  $missed
     * @param  list<string>  $unexpected
     */
    public function __construct(
        public readonly string $detector,
        public readonly array $missed,
        public readonly array $unexpected,
    ) {}

    public function passed(): bool
    {
        return $this->missed === [] && $this->unexpected === [];
    }
}
