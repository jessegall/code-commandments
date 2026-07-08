<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Config;

/**
 * The configurable knob for {@see \JesseGall\CodeCommandments\Detectors\Backend\RepeatedNamedCallDetector}:
 * how many times the same `with`-style named-argument call must recur (on the same resolved method) before
 * it's flagged as a missing helper. Defaults to 2 — a shape written twice is already a pattern.
 */
trait RepeatedNamedCallConfig
{
    private int $threshold = 2;

    public function threshold(int $times): static
    {
        $this->threshold = max(2, $times);

        return $this;
    }
}
