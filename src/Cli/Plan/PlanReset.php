<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\PlanProfile;

/**
 * The single clean-slate for a worktree's plan: wipes EVERY piece of per-plan state — the active
 * marker (and its stuck signal), the run's constraints, the testing choice, the working-state record, and
 * all hook counters. One home so the two moments that must start fresh — a brand-new session
 * ({@see \JesseGall\CodeCommandments\Hooks\Handlers\SessionReset}) and a newly-approved plan
 * ({@see \JesseGall\CodeCommandments\Hooks\Handlers\PlanReminder}) — reset the exact same set, never a
 * hand-copied subset that drifts. A previous plan's counters or notes can never bleed into the next one.
 */
final class PlanReset
{
    public static function wipe(string $root, PlanProfile $plan): void
    {
        PlanMarker::inWorktree($root)->clear();
        PlanConstraints::inWorktree($root, $plan)->clear();
        PlanTesting::inWorktree($root, $plan)->clear();
        PlanWorkingState::inWorktree($root)->clear();

        Counter::clearAll($root);
    }
}
