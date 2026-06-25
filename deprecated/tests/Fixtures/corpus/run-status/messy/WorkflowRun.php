<?php

namespace App\RunStatus;

use Carbon\CarbonImmutable;

/**
 * One execution of a workflow: which workflow, who triggered it, and the status
 * it currently holds.
 *
 * ROOT SMELL: the run's status is a bare `string`. Nothing constrains it to the
 * real lifecycle states, nothing carries the transition/terminality rules, and
 * every consumer downstream has to re-derive that knowledge by comparing against
 * literal strings — 'running', 'failed', 'completed' — scattered across the app
 * and drifting out of sync.
 */
final readonly class WorkflowRun
{
    public function __construct(
        public string $id,
        public string $workflowId,
        public string $triggeredBy,
        public string $status,
        public CarbonImmutable $startedAt,
    ) {}

    public function withStatus(string $status): self
    {
        return new self(
            id: $this->id,
            workflowId: $this->workflowId,
            triggeredBy: $this->triggeredBy,
            status: $status,
            startedAt: $this->startedAt,
        );
    }
}
