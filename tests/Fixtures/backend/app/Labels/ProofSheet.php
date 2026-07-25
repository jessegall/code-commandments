<?php

namespace Shop\Labels;

/**
 * Renders a proof sheet for the operator to check before the real run.
 */
final class ProofSheet
{
    public function __construct(private readonly PrintDensity $density) {}

    public function dpi(DraftJob $job): int
    {
        return $this->density->forDraft($job->isProof());
    }
}
