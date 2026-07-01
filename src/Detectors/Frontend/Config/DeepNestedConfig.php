<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend\Config;

/**
 * The tunable knobs of {@see \JesseGall\CodeCommandments\Detectors\Frontend\DeepNestedDetector},
 * kept off the detector so its class shows only what matters at a glance — the sin, the scribe,
 * and the find. A project tunes them via
 * `$config->configure(fn (DeepNestedDetector $d) => $d->maxDepth(10))`.
 */
trait DeepNestedConfig
{
    /** Nested DEEPER than this is too deep. */
    private int $maxDepth = 8;

    /** ...and worth extracting only with MORE levels than this still beneath it. */
    private int $maxRemaining = 3;

    public function maxDepth(int $levels): static
    {
        $this->maxDepth = $levels;

        return $this;
    }

    public function maxRemaining(int $levels): static
    {
        $this->maxRemaining = $levels;

        return $this;
    }
}
