<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * One detector's verdict against the fixture's #[Sinful] markers: missed (false
 * negatives) and unexpected (false positives).
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
