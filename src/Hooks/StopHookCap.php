<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

/**
 * How many stops in a row a hook may hold before the HARNESS stops listening. Claude Code counts
 * consecutive `Stop` hooks answering `decision: block` and, past its cap, overrides the hook and
 * ends the turn — an override that keeps NOTHING, so a gate still holding is simply dropped and the
 * user's conditions leave the session unsaid. A hook that blocks therefore stands down first: it
 * asks for its {@see budget} and stops one short of the cap, whatever the cap happens to be. That is
 * also the answer to the harness's `stop_hook_active` flag, which suits a hook that nudges once —
 * passing while it is set would hold one stop and wave every later one through, which is no gate.
 */
final class StopHookCap
{
    /**
     * The environment variable Claude Code reads for its cap, so a user who raises it there raises
     * the budget here with it and the two never drift apart.
     */
    public const string VARIABLE = 'CLAUDE_CODE_STOP_HOOK_BLOCK_CAP';

    /**
     * The cap Claude Code applies when nothing sets one.
     */
    private const int DEFAULT = 9;

    /**
     * The harness's cap for this session.
     */
    public static function cap(): int
    {
        $configured = getenv(self::VARIABLE);

        return $configured === false || (int) $configured < 1 ? self::DEFAULT : (int) $configured;
    }

    /**
     * How many consecutive holds a hook may take, given the ceiling it wants for itself.
     *
     * One short of the cap, so the hook's own release always runs first. A hook that wants fewer
     * keeps its own number: this is a ceiling, never a target.
     */
    public static function budget(int $wanted): int
    {
        return min($wanted, self::cap() - 1);
    }
}
