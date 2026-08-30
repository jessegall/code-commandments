<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Support\Binary;
use JesseGall\CodeCommandments\Workspace;

/**
 * A commit has landed, so there is something fixed to read — a diff against the working tree moves under
 * its reader where a sha does not. This STARTS the reviewer rather than asking somebody to: a hook that
 * hands over an instruction depends on the reader obeying it, and the reader is busy doing the thing that
 * produced the commit. It fires only where the profile in force declares the role.
 */
final class CommitReview extends Hook
{
    /**
     * The switch a profile turns on to get commit review, and the role it hands the commit to when it
     * names none. The profile decides both — a hook that reviewed every commit everywhere would be the
     * package choosing a way of working on a project's behalf.
     */
    private const string FEATURE = 'commit-review';

    private const string ROLE = 'ponytail';

    /**
     * Where the reviewer's own output goes. It runs detached, so its words must land somewhere a person
     * can read them rather than on a stream nothing is attached to.
     */
    private const string LOG = '.ponytail.log';

    public function summary(): string
    {
        return 'After a commit lands, starts the profile\'s reviewer in the background against that sha.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PostToolUse')];
    }

    /**
     * Never blocks. The commit is already made, so the review is worth having and not worth holding the
     * next command for — and a reviewer that runs detached cannot fail the thing it reviews.
     */
    protected function onPostToolUse(HookEvent $event): int
    {
        if (! $event->isGitCommit()) {
            return $this->pass();
        }

        foreach (Profiles::inForce($event->sessionWorkspace()) as $profile) {
            foreach ($profile->settings(self::FEATURE) as $declared) {
                $role = is_string($declared['role'] ?? null) ? $declared['role'] : self::ROLE;

                foreach ($profile->role($role) as $ignored) {
                    return $this->start($event, $role);
                }

                return $this->pass(); // Turned on, but naming a role this profile has not written.
            }
        }

        return $this->pass();
    }

    /**
     * Spawn the reviewer detached. The FIRST commit opens its session; every one after CONTINUES it, so
     * the reviewer keeps what it has learned about this codebase across a build rather than meeting it
     * fresh each time — a reader who has seen the last five commits is the one worth having.
     */
    private function start(HookEvent $event, string $role): int
    {
        $sha = $this->git()->head($event->root);
        $state = $this->state($event->sessionWorkspace());
        $held = $state->read();
        $session = $held->text('session');
        $opened = $session !== '';
        $log = $event->sessionWorkspace()->path(self::LOG);

        // Its OWN session, named by us and resumed BY ID. `--continue` picks the most recent conversation
        // in the directory, which is the ORCHESTRATOR's — so the reviewer would have no independence from
        // the context that wrote the code, and every commit would spend the very attention the role exists
        // to protect.
        $session = $opened ? $session : $this->newSessionId();

        exec(sprintf(
            'cd %s && nohup claude %s %s -p %s >> %s 2>&1 &',
            escapeshellarg($event->root),
            $opened ? '--resume' : '--session-id',
            escapeshellarg($session),
            escapeshellarg($this->brief($event, $sha, $opened, $role)),
            escapeshellarg($log),
        ));

        $state->write($held->with(session: $session, last_sha: $sha));

        return $this->quietly($event, sprintf(
            'Code Commandments — the `%s` is reading %s in the background (%s). Its report lands in %s.',
            $role,
            substr($sha, 0, 7),
            $opened ? 'continuing its own session' : 'a new session, opened now',
            $event->sessionWorkspace()->relative(self::LOG),
        ));
    }

    /**
     * A session id for the reviewer to live in. Version 4, because the harness wants a UUID and one we
     * choose is the only way to reach the same conversation again from a hook that keeps no handles.
     */
    private function newSessionId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * What the reviewer is told. A CONTINUING one already holds the brief and everything it has learned,
     * so restating the standard would spend its context re-reading what it knows.
     */
    private function brief(HookEvent $event, string $sha, bool $opened, string $role): string
    {
        $binary = Binary::in($event->root);

        if ($opened) {
            return "Another commit landed: {$sha}. Review it the same way — `git show {$sha}`. Report only "
                . 'what is UNIDIOMATIC; "this commit is idiomatic" is a complete answer.';
        }

        return <<<TEXT
            You are the `{$role}` for this codebase. Read your brief first — it is the standard you judge
            by, not your own taste:

              {$binary} orchestrate show --role={$role}

            Then review ONE commit: `git show {$sha}`. That is the whole input.

            You are not hunting bugs; the tests and `judge` do that. You are the reader who says "that
            works, and it is not how we do it here", and can say why. Look hardest for a fact declared
            twice, a caller reaching around whatever owns the answer, a name that has stopped being true,
            and a local shape where the codebase already has a word.

            Report only — never edit, commit or fix. Say nothing rather than pad: "this commit is
            idiomatic" is a complete report and should be the usual one.

            You will be sent later commits in this same session, so what you learn now is worth keeping.
            TEXT;
    }

    /**
     * Whether the reviewer's session is already open, and what it last read. Session-scoped, because a
     * new session has no reviewer to continue and should open one rather than resume a conversation that
     * belonged to a different build.
     */
    private function state(Workspace $workspace): StateFile
    {
        return new StateFile($workspace->path('.ponytail'), new Legend(
            'The background reviewer started after each commit (`ponytail`). Deleting this only means the '
                . 'next commit opens a fresh reviewer instead of continuing the one already reading.',
            [
                'session' => 'the id of the reviewer\'s OWN conversation — empty until one has been '
                    . 'opened. Resumed by this id rather than by "the most recent conversation", which '
                    . 'would be the orchestrator\'s own',
                'last_sha' => 'the commit it was most recently handed',
            ],
            defaults: new State(session: '', last_sha: ''),
        ));
    }
}
