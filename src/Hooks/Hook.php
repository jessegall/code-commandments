<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

use JesseGall\CodeCommandments\Hooks\Handlers\Remind;
use JesseGall\CodeCommandments\Hooks\Handlers\JudgeReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\PlanReminder;
/**
 * Base class for all code-commandments hooks. Subclasses declare the moments they wire;
 * {@see handle} dispatches to moment handlers. Responses use {@see block}, {@see inject},
 * {@see pass} for uniform behavior across hooks.
 */
abstract class Hook
{
    public function __construct(protected readonly HookIO $io = new HookIO) {}

    /**
     * Where this hook binds into Claude Code's settings — the (event, matcher) pairs {@see Hooks}
     * wires it under. A hook declares its own, so the wiring is data-driven from the classes; a
     * consumer's registered hook joins the set just by returning its bindings here.
     *
     * @return list<HookBinding>
     */
    abstract public function bindings(): array;

    /**
     * A one-line description of what this hook does — the single source for the README hooks table. Empty
     * by default; every builtin overrides it, and a test enforces that so a wired hook can't ship
     * undocumented.
     */
    public function summary(): string
    {
        return '';
    }

    final public function run(array $args): int
    {
        return $this->handle(new HookEvent($this->io->payload(), $this->io->projectRoot()));
    }

    /**
     * Dispatch by event name to the moment handlers. A bare CLI run has no event name and falls to
     * {@see onManualRun}. One rule holds for EVERY Stop hook and lives here, not in each: a Stop that
     * is only the agent PARKED WAITING on background work (a `run_in_background` task, a subagent) is
     * not a real stop — it will auto-resume — so no `onStop` handler runs; nudging or blocking it would
     * be noise and could burn the block cap on stops the agent doesn't control.
     *
     * A second rule holds for every hook and lives here too: a hook firing INSIDE a spawned subagent stays
     * silent. Our reminders and injections speak only to the main session that owns the plan and working
     * state; in a read-only exploration subagent they are noise that can derail its task ({@see
     * HookEvent::isSubagent}).
     */
    protected function handle(HookEvent $event): int
    {
        if ($event->isSubagent()) {
            return $this->pass();
        }

        return match ($event->name()) {
            'PostToolUse' => $this->onPostToolUse($event),
            'UserPromptSubmit' => $this->onUserPromptSubmit($event),
            'PreToolUse' => $this->onPreToolUse($event),
            'SessionStart' => $this->onSessionStart($event),
            'PreCompact' => $this->pass(),
            'Stop' => $event->hasPendingBackgroundWork() ? $this->pass() : $this->onStop($event),
            default => $this->onManualRun($event),
        };
    }

    protected function onPostToolUse(HookEvent $event): int
    {
        return $this->pass();
    }

    protected function onSessionStart(HookEvent $event): int
    {
        return $this->pass();
    }

    /**
     * The user has just spoken — the ONE moment a hook can react to what the human said mid-run.
     */
    protected function onUserPromptSubmit(HookEvent $event): int
    {
        return $this->pass();
    }

    protected function onPreToolUse(HookEvent $event): int
    {
        return $this->pass();
    }

    protected function onStop(HookEvent $event): int
    {
        return $this->pass();
    }

    /**
     * A manual `commandments <hook>` run with no payload — defaults to the {@see onStop} path, the
     * safe "check now" behaviour for the reminder hooks.
     */
    protected function onManualRun(HookEvent $event): int
    {
        return $this->onStop($event);
    }

    /**
     * Block-and-continue: Claude sees $reason and gets one more turn. The hook's exit is 0.
     */
    protected function block(string $reason): int
    {
        $this->io->block($reason);

        return 0;
    }

    /**
     * Inject $context non-blockingly for this event: the tool/turn proceeds, Claude reads it.
     */
    protected function inject(HookEvent $event, string $context): int
    {
        $this->io->inject($event->name(), $context);

        return 0;
    }

    /**
     * Stay silent — emit nothing.
     */
    protected function pass(): int
    {
        return 0;
    }

    /**
     * Git reads scoped to the worktree the hook fired in.
     */
    protected function git(): GitFiles
    {
        return $this->io->git();
    }
}
