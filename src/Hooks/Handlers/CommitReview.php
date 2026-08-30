<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Support\Binary;

/**
 * A commit has landed, so there is something fixed to read — a diff against the working tree moves under
 * its reader where a sha does not. It fires only where the profile in force declares the role, and names
 * the sha rather than "the last commit", since by the time anyone acts there may be another.
 */
final class CommitReview extends Hook
{
    /**
     * The role this hands the commit to. A profile without it is a project that has not asked for
     * commit review, and the hook stays silent rather than proposing a role nobody wrote.
     */
    private const string ROLE = 'ponytail';

    public function summary(): string
    {
        return 'After a commit lands, hands its sha to the profile\'s reviewer role, when one is declared.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PostToolUse')];
    }

    /**
     * A nudge, never a gate. The commit is already made and the review is worth having, not worth
     * blocking on — a reviewer held in front of the next command is one that gets skipped in a hurry.
     */
    protected function onPostToolUse(HookEvent $event): int
    {
        if (! $event->isGitCommit()) {
            return $this->pass();
        }

        foreach (Profiles::inForce($event->sessionWorkspace()) as $profile) {
            foreach ($profile->role(self::ROLE) as $ignored) {
                return $this->inject($event, $this->handOver($event));
            }
        }

        return $this->pass();
    }

    /**
     * What the agent is told. It names the sha, the role, and where the brief is — an instruction that
     * makes the reader look three things up is one that gets postponed.
     */
    private function handOver(HookEvent $event): string
    {
        $binary = Binary::in($event->root);
        $sha = $this->git()->head($event->root);
        $role = self::ROLE;

        return <<<TEXT
            Code Commandments — {$sha} just landed. Hand it to the `{$role}`: a reviewer for what is
            UNIDIOMATIC in it, which is the one thing the tests and `judge` cannot see.

            Dispatch one worker with `git show {$sha}` as its whole input, and give it the brief:
            `{$binary} orchestrate show --role={$role}`.

            It is looking for a fact declared twice, a caller reaching around whatever owns the answer,
            a name that has stopped being true, and a local shape where the codebase already has a word.
            "This commit is idiomatic" is a complete answer and should be the usual one.
            TEXT;
    }
}
