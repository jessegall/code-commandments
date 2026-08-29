<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Workspace;

/**
 * Who owns the stop right now. Two mechanisms can hold an agent's turn — an active plan and the stop gate
 * — and each must know whether the other is speaking, or both nudge and neither is heeded. The question
 * lives ABOVE them because it belongs to neither: asked from inside either, each imported the other,
 * which is a cycle and reads correctly as two things that cannot be understood apart.
 */
final readonly class StopOwner
{
    public function __construct(private Workspace $workspace) {}

    /**
     * Is a plan holding the stop? While one is, the gate stays silent — a plan is being ground to
     * completion and its conditions take over at `plan done`.
     */
    public function isAPlan(): bool
    {
        return PlanMarker::inSession($this->workspace)->isActive();
    }
}
