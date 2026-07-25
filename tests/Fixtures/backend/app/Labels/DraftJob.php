<?php

namespace Shop\Labels;

/**
 * A queued label job, and the only thing that knows whether it is a proof run.
 */
final class DraftJob
{
    public function __construct(private readonly string $stage) {}

    public function isProof(): bool
    {
        return $this->stage === 'proof';
    }
}
