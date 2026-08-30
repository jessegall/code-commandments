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
     * be noise and could burn the block cap on stops the agent doesn't control. The same holds for a
     * Stop in PLAN MODE ({@see HookEvent::isPlanMode}): the agent is presenting a plan for approval,
     * not abandoning work — no Stop handler may hold or nudge it.
     *
     * A hook firing INSIDE a spawned subagent stays silent BY DEFAULT: a reminder speaks to the session
     * that owns the plan and the working state, and inside a read-only exploration agent it is noise that
     * can derail the task. A hook that ENFORCES says otherwise ({@see speaksToSubagents}) — a refusal has
     * to run where the work happens, and under orchestration the work happens in subagents.
     */
    protected function handle(HookEvent $event): int
    {
        if ($event->isSubagent() && ! $this->speaksToSubagents()) {
            return $this->pass();
        }

        return match ($event->name()) {
            'PostToolUse' => $this->onPostToolUse($event),
            'UserPromptSubmit' => $this->onUserPromptSubmit($event),
            'PreToolUse' => $this->onPreToolUse($event),
            'SessionStart' => $this->onSessionStart($event),
            'MessageDisplay' => $this->onMessageDisplay($event),
            'PreCompact' => $this->onPreCompact($event),
            'PostCompact' => $this->onPostCompact($event),
            'Stop' => $this->staysQuietAt($event) ? $this->pass() : $this->onStop($event),
            'SubagentStop' => $this->onSubagentStop($event),
            default => $this->onManualRun($event),
        };
    }

    /**
     * Does this hook speak inside a spawned subagent? A REMINDER does not: it addresses the session that
     * owns the plan, and an exploration agent hearing it is being derailed by somebody else's business. A
     * REFUSAL does, because the thing it refuses is done by the worker, and a rule that goes quiet exactly
     * where the work happens is not a rule.
     */
    protected function speaksToSubagents(): bool
    {
        return false;
    }

    /**
     * Should this hook hold its tongue at THIS stop? Plan mode always, since nothing has been approved
     * yet — and pending background work only for a hook that would tell the agent to carry on, which is
     * already what it is doing.
     */
    private function staysQuietAt(HookEvent $event): bool
    {
        return $event->isPlanMode() || ($event->hasPendingBackgroundWork() && ! $this->speaksWhileWorkPends());
    }

    /**
     * Does this hook still have something to say while a background task is running?
     *
     * YES for almost everything, and the default says so. A stop condition is unmet whether or not a
     * suite is running; a routine is a checklist for the moment work comes to rest; an unclosed span is
     * still unclosed. Silencing every Stop hook because SOMETHING is pending muted all of them for a
     * whole session — 415 pieces of work, not one stop held — which is the same blanket that
     * {@see speaksToSubagents} exists to undo one event over.
     *
     * NO only for a nudge to KEEP GOING, which is redundant advice to an agent that already is.
     */
    protected function speaksWhileWorkPends(): bool
    {
        return true;
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

    /**
     * A batch of newly completed lines of an assistant message, as it streams. The ONE moment a hook can
     * see what the agent itself is saying — every other event reports on tools and turns. Display-only:
     * nothing emitted here reaches the model or changes the stored message, so this moment RECORDS and
     * never speaks. It fires per flush, several times per message, so a handler must be cheap.
     */
    protected function onMessageDisplay(HookEvent $event): int
    {
        return $this->pass();
    }

    /**
     * The context is ABOUT to be compacted. Two things are possible here and no others: BLOCK (which
     * cancels the compaction outright) and plain STDOUT, which the harness takes verbatim as the
     * compaction's own custom instructions ({@see HookIO::instruct}) — so this is the one moment a hook
     * can tell the summariser what must survive. There is no context channel; see {@see HookIO::INJECTABLE}.
     */
    protected function onPreCompact(HookEvent $event): int
    {
        return $this->pass();
    }

    /**
     * The compaction has happened and $event->compactSummary() is what it produced. Nothing said here
     * reaches the model — only the user's display — so this moment records the boundary and no more. The
     * far side is spoken to at `SessionStart` with source `compact`, which compaction re-fires.
     */
    protected function onPostCompact(HookEvent $event): int
    {
        return $this->pass();
    }

    protected function onStop(HookEvent $event): int
    {
        return $this->pass();
    }

    /**
     * A spawned agent stopped. Distinct from {@see onStop}, which is the SESSION coming to rest — this is
     * one worker finishing while the session carries on, and it is the only measurement the harness gives
     * of a completion. Everything else about a worker's fate has to be inferred.
     */
    protected function onSubagentStop(HookEvent $event): int
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
     * Inject $context QUIETLY: the agent reads it, the user's transcript does not show it. The shape
     * a heartbeat wants — a reminder that fires on tool use should not fill the terminal.
     */
    protected function quietly(HookEvent $event, string $context): int
    {
        $this->io->inject($event->name(), $context, quietly: true);

        return 0;
    }

    /**
     * Write the instructions this compaction is to be summarised under — the `PreCompact` reply that is not
     * a block ({@see HookResponse::instructing}).
     */
    protected function instruct(string $text): int
    {
        $this->io->emit(HookResponse::instructing($text), 'PreCompact');

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
