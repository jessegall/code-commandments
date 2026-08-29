<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Orchestration;

/**
 * Who is holding an item, and since when. It is the half of a {@see Claim} that does NOT move: a worker
 * reports, is reworked and reports again, and through all of it the hold is the same hold — which is the
 * whole point, since a hold that ended when a process did would free the item in the window the
 * orchestrator is deciding what to hand out next.
 */
final readonly class Hold
{
    public function __construct(
        public string $holder,
        public string $since,
    ) {}

    public function isBy(string $holder): bool
    {
        return $this->holder === $holder;
    }
}
