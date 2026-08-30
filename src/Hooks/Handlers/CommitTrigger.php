<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Dispatcher;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Support\File;

/**
 * The `commit` moment. A commit has landed, so there is something FIXED to read — a diff against the
 * working tree moves under its reader where a sha does not. What RUNS is the profile's to say
 * (`orchestrate on commit <agent> <procedure>`); this knows the moment and nothing else.
 */
final class CommitTrigger extends Hook
{
    /**
     * The moment this answers, as a profile names it.
     */
    private const string TRIGGER = 'commit';

    public function summary(): string
    {
        return 'When a commit lands on this checkout, dispatches whatever the profile bound to the `commit` trigger.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PostToolUse')];
    }

    /**
     * Never blocks. The commit is already made, so what follows is worth having and not worth holding the
     * next command for.
     */
    protected function onPostToolUse(HookEvent $event): int
    {
        if (! $event->isGitCommit() || ! $this->landedHere($event)) {
            return $this->pass();
        }

        $said = new Dispatcher($event->sessionWorkspace(), $event->root)
            ->fire(self::TRIGGER, $this->git()->head($event->root));

        // SEEN, not quiet. This starts a process on somebody's machine — an agent that appears, reads
        // their code and writes files is the one thing here a person must never learn about by finding
        // it in a log afterwards. Every other reminder is for the agent; this one is for them.
        return $said === [] ? $this->pass() : $this->inject($event, implode("\n", $said));
    }

    /**
     * Did the commit land on THIS checkout? A worktree of the same repository is the same repository, so
     * a builder checkpointing in a lane and this session committing are one event to a hook — and lane
     * commits are drafts by design: they exist so work in flight cannot be mistaken for work never done,
     * not to be presented. Three lanes checkpointing per widget is fifteen reviews for what lands as
     * three merges, each against a tree its author has already moved past.
     *
     * Answered from HEAD, never from the cwd: a hook fires in the session's own process wherever the
     * commit was made, so whether THIS checkout's head moved is the only thing separating them. It also
     * settles a dispatched agent committing inside its own lane, which would otherwise trigger a review
     * of a review.
     */
    private function landedHere(HookEvent $event): bool
    {
        $head = $this->git()->head($event->root);
        $seen = $event->sessionWorkspace()->path('.commit-trigger-head');
        $last = is_file($seen) ? trim((string) file_get_contents($seen)) : '';

        if ($head === '' || $head === $last) {
            return false;
        }

        File::write($seen, $head);

        return true;
    }
}
