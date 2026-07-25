<?php

namespace Shop\Labels;

/**
 * Writes the spool header the printer reads before the first label.
 */
final class SpoolWriter
{
    public function __construct(private readonly PrintDensity $density) {}

    public function header(DraftJob $job): string
    {
        $dpi = $this->density->forDraft($job->isProof());

        return sprintf('@DENSITY %d', $dpi);
    }
}
