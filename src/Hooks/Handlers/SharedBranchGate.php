<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

/**
 * Refuses a rebase of a branch other worktrees are standing on — needing nothing declared, since git
 * already knows they exist and that is the whole condition. A rebase rewrites the commits they are built
 * on, so each ends up holding commits the branch no longer has, byte-identical and silent until a merge.
 */
final class SharedBranchGate extends Hook
{
    /**
     * The rewrite this exists for. A pull that rebases is the one that mints byte-identical duplicates of
     * commits other worktrees already hold.
     */
    private const array REWRITES = ['--rebase', '-r'];

    /**
     * A command carrying one of these may be WRITING the rule rather than running it — a heredoc, a quoted
     * string, a file being generated. The text of such a command is not a command, so it is never judged:
     * refusing somebody for describing a command is the kind of refusal that gets a tool uninstalled.
     */
    private const array QUOTES = ['<<', "'", '"'];

    /**
     * How `git worktree list --porcelain` announces each one.
     */
    private const string DECLARED = 'worktree ';

    public function summary(): string
    {
        return 'Refuses `git pull --rebase` while other worktrees stand on the branch — it rewrites the commits they are built on.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PreToolUse', 'Bash')];
    }

    /**
     * A refusal has to run where the work happens, and under orchestration the work happens in workers.
     */
    protected function speaksToSubagents(): bool
    {
        return true;
    }

    protected function onPreToolUse(HookEvent $event): int
    {
        if (! $event->isTool('Bash') || ! $this->rewritesHistory($event->command())) {
            return $this->pass();
        }

        $others = $this->otherWorktrees($event->root);

        if ($others === []) {
            return $this->pass(); // Nobody else is standing on it; a rebase harms nobody.
        }

        $standing = implode("\n", array_map(fn (string $path) => '  • ' . $path, $others));
        $count = count($others);

        return $this->block(<<<TEXT
            Code Commandments — that rebases a branch {$count} other worktree(s) are standing on:

            {$standing}

            A rebase rewrites the commits they are built on, so each ends up carrying commits this branch
            no longer has — and the duplicates are byte-identical, so nothing looks wrong until a merge.
            Use `git pull --ff-only`, or merge, and let them fast-forward.
            TEXT);
    }

    /**
     * Does $command rewrite history that others may hold? Only an unambiguous invocation counts: each
     * `&&`-separated part must ITSELF begin `git` and pull with a rebase flag. A mention inside a heredoc
     * or a quoted string is somebody writing about the command, not running it — this very file tripped
     * that on the commit that introduced it.
     */
    private function rewritesHistory(string $command): bool
    {
        foreach (self::QUOTES as $quote) {
            if (! str_contains($command, $quote)) {
                continue;
            }

            return false; // The text may be quoted; a command we cannot read plainly is not judged.
        }

        foreach (explode('&&', $command) as $part) {
            if ($this->isRebasingPull(trim($part))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this one command `git pull` with a rebase flag?
     */
    private function isRebasingPull(string $part): bool
    {
        $words = preg_split('/\s+/', $part) ?: [];

        if (($words[0] ?? '') !== 'git' || ! in_array('pull', $words, true)) {
            return false;
        }

        foreach (self::REWRITES as $flag) {
            if (! in_array($flag, $words, true)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Every worktree of this repository except the one the command is running in.
     *
     * @return list<string>
     */
    private function otherWorktrees(string $root): array
    {
        $listing = (string) @shell_exec('git -C ' . escapeshellarg($root) . ' worktree list --porcelain 2>/dev/null');
        $others = [];

        foreach (explode("\n", $listing) as $line) {
            if (! str_starts_with($line, self::DECLARED)) {
                continue;
            }

            $others[] = substr($line, strlen(self::DECLARED));
        }

        return array_values(array_filter($others, fn (string $path) => realpath($path) !== realpath($root)));
    }
}
