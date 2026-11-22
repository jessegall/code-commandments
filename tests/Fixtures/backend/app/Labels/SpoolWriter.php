<?php

namespace Shop\Labels;

use JesseGall\CodeCommandments\Sins\Backend\ComputedBooleanArgument;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Writes the spool header the printer reads before the first label.
 */
final class SpoolWriter
{
    public function __construct(private readonly PrintDensity $density) {}

    #[Sinful(ComputedBooleanArgument::class)]
    public function header(DraftJob $job): string
    {
        $dpi = $this->density->forDraft($job->isProof());

        return sprintf('@DENSITY %d', $dpi);
    }
}
