<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * How autonomously a project runs an approved plan — the single knob {@see PlanExecution::mode} sets, read
 * by the keep-going {@see \JesseGall\CodeCommandments\Hooks\Handlers\PlanReminder} hook. It decides two
 * things: whether the agent CONFIRMS before implementing, and what a Stop does while the plan is unfinished.
 * Not setting a mode leaves plan runs unmanaged (no gate, no keep-going) — the human's stop always stands.
 */
enum PlanMode
{
    /**
     * Confirm first. On approval the agent presents the plan and ASKS the user before writing any code; a
     * Stop is never overridden. For work you want a checkpoint on before it starts running.
     */
    case Ask;

    /**
     * Supervised grind: nudge to continue at most ONCE, then honour a Stop. The agent keeps going on its
     * own, but a human stop is respected — for a plan you want to watch, not leave running unattended.
     */
    case Supervised;

    /**
     * Grind to the finish. Every Stop re-injects "keep going" until the plan is done. When the agent is
     * GENUINELY blocked it runs `plan stuck` to pause the nudge and get the user — a supervised escape hatch.
     */
    case Autonomous;

    /**
     * NEVER stop. Grind every phase to completion; a Stop is always overridden (only the runaway backstop
     * can end it). There is NO waiting for the user: a genuinely-blocked phase is SKIPPED and recorded, not
     * paused on — `plan stuck` is disabled. Choose the best option yourself and keep moving. For a plan you
     * want run start-to-finish with zero human turns.
     */
    case Relentless;

    /**
     * Does a Stop re-nudge the agent to continue? True for every mode that runs autonomously; false for
     * {@see Ask}, which is a start-gate, not a keep-going posture.
     */
    public function keepsGoing(): bool
    {
        return $this === self::Supervised || $this === self::Autonomous || $this === self::Relentless;
    }

    /** Nudge exactly once, then let a Stop stand — the {@see Supervised} posture. */
    public function nudgesOnce(): bool
    {
        return $this === self::Supervised;
    }

    /** Is `plan stuck` an available pause — true only for {@see Autonomous}, where blocking to ask is allowed. */
    public function allowsStuck(): bool
    {
        return $this === self::Autonomous;
    }

    /** Never honour a Stop until the plan is done (skip blockers, don't wait) — the {@see Relentless} posture. */
    public function neverStops(): bool
    {
        return $this === self::Relentless;
    }
}
