<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Cli\Plan\PlanReset;
/**
 * The fresh-session cleanup — a `SessionStart` hook that wipes the session's lingering plan state (the
 * {@see PlanMarker}, constraints, testing choice, working-state record, reminder counters) so a crashed or force-closed run
 * never leaves the keep-going Stop hook nudging a brand-new session to "keep grinding the plan", and
 * prunes stale sibling session folders ({@see \JesseGall\CodeCommandments\Workspace::prune}) so
 * `.commandments/sessions/` never grows forever. It fires
 * only for a genuinely-new session ({@see FRESH_SESSION_SOURCES}); `resume`/`compact` continue a live one
 * — and compaction re-fires `SessionStart`, so wiping there would drop an in-flight plan.
 */
final class SessionReset extends Hook
{
    /** SessionStart sources that begin a genuinely-new session — the only ones we clean up on. */
    private const array FRESH_SESSION_SOURCES = ['startup', 'clear'];

    public function summary(): string
    {
        return "On a fresh session (startup/clear) wipes lingering plan state, so a crashed run never nudges a new session.";
    }

    public function bindings(): array
    {
        return [new HookBinding('SessionStart')];
    }

    protected function onSessionStart(HookEvent $event): int
    {
        if (! in_array($event->source(), self::FRESH_SESSION_SOURCES, true)) {
            return $this->pass(); // resume / compact continue a live session — leave its plan intact.
        }

        $workspace = $event->workspace();

        PlanReset::wipe($workspace, Config::load($event->root)->planExecutionSettings());
        $workspace->prune(); // Sweep session folders long abandoned — never this session's own.

        return $this->pass(); // Silent — a cleanup has nothing to say to the fresh session.
    }
}
