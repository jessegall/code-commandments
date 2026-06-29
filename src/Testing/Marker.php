<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * A `#[Sinful]` marker read off the fixture: which detector must flag it, and
 * the declaration scope it covers. Absence of a marker means the spot is clean
 * for every detector — so the fixture as a whole is the false-positive guard.
 */
final class Marker
{
    public function __construct(
        public readonly string $detector,
        public readonly string $class,
        public readonly ?string $method,
        public readonly string $location,
    ) {}

    /**
     * Does a finding in the given class/method fall under this marker? A
     * class-level marker (no method) covers the whole class.
     */
    public function covers(string $class, ?string $method): bool
    {
        if ($this->class !== $class) {
            return false;
        }

        return $this->method === null || $this->method === $method;
    }
}
