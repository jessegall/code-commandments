<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * The persisted state of an active plan — the base branch it was cut from, the HEAD at the last
 * keep-going nudge, and the two nudge counters ({@see PlanMarker} reads/writes it). `stuck` is the
 * run of nudges since HEAD last moved (progress resets it); `total` is every nudge ever.
 */
final readonly class PlanState
{
    public function __construct(
        public string $base,
        public string $head,
        public int $stuck,
        public int $total,
    ) {}

    /**
     * The next state after a nudge at $head: `stuck` climbs while HEAD is unchanged and resets to 1
     * the moment it moves (progress); `total` always climbs.
     */
    public function nudged(string $head): self
    {
        $progressed = $head !== '' && $head !== $this->head;

        return new self($this->base, $head, $progressed ? 1 : $this->stuck + 1, $this->total + 1);
    }
}
