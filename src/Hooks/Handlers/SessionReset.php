<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

/**
 * The fresh-session cleanup — a `SessionStart` hook that wipes the session's lingering hook counters,
 * so a crashed or force-closed run never hands its heartbeats to a brand-new session, and prunes
 * stale sibling session folders ({@see \JesseGall\CodeCommandments\Workspace::prune}) so
 * `.commandments/sessions/` never grows forever. It fires only for a genuinely-new session
 * ({@see FRESH_SESSION_SOURCES}); `resume`/`compact` continue a live one — and compaction re-fires
 * `SessionStart`, so wiping there would reset counters mid-run.
 */
final class SessionReset extends Hook
{
    /**
     * SessionStart sources that begin a genuinely-new session — the only ones we clean up on.
     */
    private const array FRESH_SESSION_SOURCES = ['startup', 'clear'];

    public function summary(): string
    {
        return 'On a fresh session (startup/clear) wipes lingering hook counters and prunes stale session folders.';
    }

    public function bindings(): array
    {
        return [new HookBinding('SessionStart')];
    }

    protected function onSessionStart(HookEvent $event): int
    {
        if (! in_array($event->source(), self::FRESH_SESSION_SOURCES, true)) {
            return $this->pass(); // resume / compact continue a live session — leave its counters intact.
        }

        $workspace = $event->workspace();

        Counter::clearAll($workspace);
        $workspace->prune(); // Sweep session folders long abandoned — never this session's own.

        return $this->pass(); // Silent — a cleanup has nothing to say to the fresh session.
    }
}
