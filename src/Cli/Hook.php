<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * The shape every code-commandments Claude Code hook shares — the base {@see Remind},
 * {@see JudgeReminder}, and {@see PlanReminder} extend, so a hook is written by declaring WHAT it
 * does at each moment, never by re-plumbing HOW hooks read, dispatch, and respond.
 *
 * {@see run} (the CLI entrypoint, final) reads the payload, resolves the worktree, and dispatches
 * by event name to a per-moment handler. A subclass overrides only the moments it wires — the rest
 * {@see pass stay silent}. Responses go through the small vocabulary below ({@see block},
 * {@see inject}, {@see pass}) so behaviour reads uniformly across hooks, and git access is the
 * worktree-scoped {@see git} shared by them all.
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

    final public function run(array $args): int
    {
        return $this->handle(new HookEvent($this->io->payload(), $this->io->projectRoot()));
    }

    /**
     * Dispatch by event name to the moment handlers. A bare CLI run has no event name and falls to
     * {@see onManualRun}.
     */
    protected function handle(HookEvent $event): int
    {
        return match ($event->name()) {
            'PostToolUse' => $this->onPostToolUse($event),
            'PreToolUse' => $this->onPreToolUse($event),
            'Stop' => $this->onStop($event),
            default => $this->onManualRun($event),
        };
    }

    protected function onPostToolUse(HookEvent $event): int
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
