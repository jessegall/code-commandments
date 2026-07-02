<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * How hard the keep-going Stop hook pushes while a plan is active — chosen by
 * {@see PlanExecution::keepGoing}. It decides what happens when the agent stops before the plan is
 * marked done: keep grinding, or let the human's stop stand.
 */
enum StopPolicy
{
    /**
     * Grind to the finish: every stop re-injects "keep going" until the plan is done (or the
     * loop-safety cap trips). Yield only when the agent genuinely needs user input.
     */
    case UntilComplete;

    /**
     * The human's stop is final: nudge at most once, then honour a stop. For plans you want to
     * supervise, not leave running unattended.
     */
    case RespectUserStops;
}
