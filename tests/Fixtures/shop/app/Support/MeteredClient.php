<?php

namespace Shop\Support;

/**
 * Righteous twin (NOT a masked invariant): the meter is a constructor-injected,
 * genuinely-optional collaborator — never set later in a method. Defaulting its
 * absence is a Null-Object choice (`absence` territory), not a type lying about a
 * value the design always has. The detector must leave this alone.
 */
final class MeteredClient
{
    public function __construct(private readonly ?Meter $meter = null) {}

    public function record(string $event): bool
    {
        return $this->meter?->track($event) ?? false;
    }
}

final class Meter
{
    public function track(string $event): bool
    {
        return strlen($event) > 0;
    }
}
