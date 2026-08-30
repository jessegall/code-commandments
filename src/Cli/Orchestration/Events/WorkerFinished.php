<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

use JesseGall\CodeCommandments\Agent;

/**
 * A worker STOPPED, on `SubagentStop` — the harness saying it happened, rather than completion inferred
 * from silence, since an absent row in a listing is an absence you interpret where this is a measurement.
 * It carries the AGENT and nothing about the board on purpose: a worker can finish having never claimed
 * an item, so a trigger decides whether this maps to work it knows.
 */
final readonly class WorkerFinished extends Event
{
    public function __construct(
        string $root,
        public Agent $agent,
    ) {
        parent::__construct($root);
    }

    public function label(): string
    {
        return static::class . ' — ' . $this->agent->render();
    }
}
