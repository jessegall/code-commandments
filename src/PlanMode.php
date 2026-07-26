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
     * Finish as much as possible — never ask, but be completionist. Grind every phase; a Stop is always
     * overridden (no waiting for the user). When a step is genuinely blocked it is SKIPPED and DEFERRED, not
     * paused on — the agent records it, keeps going with the rest of the plan, and RETRIES every deferred
     * step at the END before finishing. `plan stuck` is disabled (it defers instead). Between {@see Autonomous}
     * (which pauses to ask) and {@see Relentless} (which never circles back).
     */
    case BestEffort;

    /**
     * NEVER stop. Grind every phase to completion; a Stop is always overridden (only the runaway backstop
     * can end it). There is NO waiting for the user: a genuinely-blocked phase is SKIPPED and the agent moves
     * straight on — `plan stuck` is disabled. Choose the best option yourself and keep moving. For a plan you
     * want run start-to-finish with zero human turns.
     */
    case Relentless;

    /**
     * Does a Stop re-nudge the agent to continue? Every mode runs autonomously, so always true.
     */
    public function keepsGoing(): bool
    {
        return true;
    }

    /**
     * Nudge exactly once, then let a Stop stand — the {@see Supervised} posture.
     */
    public function nudgesOnce(): bool
    {
        return $this === self::Supervised;
    }

    /**
     * Is `plan stuck` an available pause — true only for {@see Autonomous}, where blocking to ask is allowed.
     */
    public function allowsStuck(): bool
    {
        return $this === self::Autonomous;
    }

    /**
     * Never honour a Stop until the plan is done — skip blockers, don't wait for the user. True for both the
     * completionist {@see BestEffort} (skip + defer + retry) and {@see Relentless} (skip + move on).
     */
    public function neverStops(): bool
    {
        return $this === self::BestEffort || $this === self::Relentless;
    }

    /**
     * Skip a blocker but DEFER it and retry at the end — the {@see BestEffort} posture (vs Relentless, which never circles back).
     */
    public function defersBlockers(): bool
    {
        return $this === self::BestEffort;
    }
}
