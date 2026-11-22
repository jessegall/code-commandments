<?php

namespace Shop\Labels;

use JesseGall\CodeCommandments\Sins\Backend\ComputedBooleanArgument;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Renders a proof sheet for the operator to check before the real run.
 */
final class ProofSheet
{
    public function __construct(private readonly PrintDensity $density) {}

    #[Sinful(ComputedBooleanArgument::class)]
    public function dpi(DraftJob $job): int
    {
        return $this->density->forDraft($job->isProof());
    }
}
