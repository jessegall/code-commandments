<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Config;

/**
 * The tunable knobs of {@see \JesseGall\CodeCommandments\Detectors\Backend\DataClumpDetector},
 * kept off the detector so its class shows only what matters at a glance — the sin and the find.
 * A project tunes them via `$config->configure(fn (DataClumpDetector $d) => $d->minClasses(3))`.
 */
trait DataClumpConfig
{
    /** The clump must recur across at least this many distinct classes to be flagged. */
    private int $minClasses = 2;

    public function minClasses(int $classes): static
    {
        $this->minClasses = $classes;

        return $this;
    }
}
