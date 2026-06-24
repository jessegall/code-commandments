<?php

namespace App\RunStatus;

use Carbon\CarbonImmutable;

/**
 * One execution of a workflow: which workflow, who triggered it, and the typed
 * status it currently holds.
 */
final readonly class WorkflowRun
{
    public function __construct(
        public string $id,
        public string $workflowId,
        public string $triggeredBy,
        public RunStatus $status,
        public CarbonImmutable $startedAt,
    ) {}

    public function withStatus(RunStatus $status): self
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
