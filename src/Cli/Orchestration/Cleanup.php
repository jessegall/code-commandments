<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;
use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
use JesseGall\CodeCommandments\Support\Directory;
use JesseGall\CodeCommandments\Workspace;

/**
 * Clears what a build leaves behind, never what a build produced. State from a version that no longer
 * exists is worse than absent — a queue holding entries for a deleted mechanism, a mark claiming a
 * process that never ran — because it reads as current while answering about a system that has moved.
 * The PROFILES, the PLAN and the JOURNAL are never touched: they outlive any run.
 */
final readonly class Cleanup
{
    /**
     * Session state a run leaves behind. Named rather than globbed so that adding a file is a decision
     * — a cleanup that removes whatever it finds is one nobody can safely extend.
     */
    private const array SWEEPS = [
        '.scheduled',
        '.watching',
        '.pending-dispatches',
        '.commit-trigger-head',
        '.ponytail.log',
        '.ponytail.prompt',
        '.scheduler.prompt',
    ];

    /**
     * Directories a run stands up and can rebuild from nothing.
     */
    private const array FOLDERS = ['worlds', 'scheduler'];

    public function __construct(
        private Workspace $workspace,
        private string $root,
        private GitFiles $git = new GitFiles,
    ) {}

    /**
     * Everything cleared, as lines a reader can check. Counting is not the point — knowing WHAT went is,
     * because a cleanup that reports "done" is one you have to verify by hand afterwards.
     *
     * @return list<string>
     */
    public function sweep(): array
    {
        $said = [];

        foreach (self::SWEEPS as $file) {
            $path = $this->workspace->path($file);

            if (is_file($path) && @unlink($path)) {
                $said[] = "  cleared  {$file}";
            }
        }

        foreach (self::FOLDERS as $folder) {
            $path = $this->workspace->path($folder);

            if (is_dir($path)) {
                Directory::delete($path);
                $said[] = "  cleared  {$folder}/";
            }
        }

        // The hook heartbeats, which are the purest example: a count kept for a nudge whose thresholds
        // have since changed answers a question nobody is asking any more.
        Counter::clearAll($this->workspace);
        $said[] = '  cleared  hook counters';

        return $said;
    }

    /**
     * Rewire the hooks from the registry as it stands TODAY. A settings file naming a class the package
     * has since deleted is fatal on the next run, and a stale entry looks exactly like a live one — this
     * repo shipped that once and it killed `sync` mid-install in a consumer.
     */
    public function rewire(): bool
    {
        return HookRegistry::wire($this->root);
    }

    /**
     * Every lane, with whether it can safely go. A lane holding uncommitted work is NAMED and left: the
     * whole reason a builder checkpoints is that committed work cannot be mistaken for work never done,
     * and deleting the uncommitted kind is the one loss nothing here can undo.
     *
     * @return array{gone: list<string>, kept: list<string>}
     */
    public function lanes(bool $andRemove): array
    {
        $gone = [];
        $kept = [];

        foreach (Checkout::lanesOf($this->root, $this->git) as $lane) {
            if ($lane->path === $this->root) {
                continue;
            }

            if ($this->hasWork($lane->path)) {
                $kept[] = "  KEPT     {$lane->name()} — uncommitted work in it";

                continue;
            }

            if (! $andRemove) {
                $kept[] = "  would go {$lane->name()}";

                continue;
            }

            @shell_exec('git -C ' . escapeshellarg($this->root) . ' worktree remove ' . escapeshellarg($lane->path) . ' 2>&1');

            $gone[] = is_dir($lane->path) ? "  KEPT     {$lane->name()} — git would not remove it" : "  removed  {$lane->name()}";
        }

        return ['gone' => $gone, 'kept' => $kept];
    }

    private function hasWork(string $lane): bool
    {
        return trim((string) @shell_exec('git -C ' . escapeshellarg($lane) . ' status --porcelain 2>/dev/null')) !== '';
    }
}
