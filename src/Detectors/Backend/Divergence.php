<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

/**
 * One path found to be another MINUS a step: which path is poorer, which it should route through, what it
 * lacks, and which of those the richer path only reaches from inside a CONDITION — a check, rather than
 * work it always does.
 */
final class Divergence
{
    /**
     * @param  list<string>  $missing  what the poorer path does not reach
     * @param  list<string>  $guards  the missing steps the richer path takes only under a condition
     */
    public function __construct(
        public readonly string $poorer,
        public readonly string $richer,
        public readonly array $missing,
        public readonly array $guards,
    ) {}

    /**
     * Does the richer path CHECK something the poorer one never does?
     */
    public function skipsACheck(): bool
    {
        return $this->guards !== [];
    }

    /**
     * How the pair reads: a path that SKIPS a check its twin makes is a change that landed in one place;
     * one that merely lacks work its twin does is the same mechanism written twice, differing by purpose.
     */
    public function describe(): string
    {
        $missing = implode(', ', $this->missing);

        return $this->skipsACheck()
            ? "skips a check {$this->richer} makes: {$missing}"
            : "the same mechanism as {$this->richer}, which also does: {$missing}";
    }
}
