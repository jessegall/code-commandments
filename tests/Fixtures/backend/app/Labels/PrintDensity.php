<?php

namespace Shop\Labels;

/**
 * Chooses the density a label prints at.
 */
final class PrintDensity
{
    /**
     * The job decides this, but the job never gets here — only its answer does, re-derived by each
     * caller. Take the job and ask it.
     */
    public function forDraft(bool $draft): int
    {
        return match ($draft) {
            true => 150,
            false => 300,
        };
    }
}
