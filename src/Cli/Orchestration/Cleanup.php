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

        // And the links this session put in the user's own agents folder. That folder outlives every
        // run, so a role published from a checkout somebody has since left would otherwise stay
        // startable for ever — the links are prefixed with this session precisely so a sweep can find
        // its own and leave every other session's alone.
        $unlinked = new UserAgents($this->workspace)->sweep();

        if ($unlinked > 0) {
            $said[] = "  cleared  {$unlinked} agent type(s) from the user's folder";
        }

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
     * Every lane, with whether it can safely go. A lane is removable only when its head is an ANCESTOR of
     * the branch — everything in it has landed, so the worktree holds nothing the branch does not.
     *
     * Dirty-versus-clean is the wrong test and it protects the wrong thing. A builder that checkpoints
     * correctly — which is what a lane is FOR, so committed work cannot be mistaken for work never done
     * — produces a clean lane holding commits nobody has merged. Judged on dirtiness, the tidiest lane
     * looks the most abandoned, and the discipline that makes work safe is what marks it for deletion.
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

            if (! $this->hasLanded($lane->path)) {
                $kept[] = "  KEPT     {$lane->name()} — commits that have not landed on the branch";

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

    /**
     * Is everything in this lane already on the branch? Asked of git rather than inferred: an ancestor
     * check is the only question whose answer cannot be wrong, where age, cleanliness and whether anybody
     * holds it are all proxies for it.
     */
    private function hasLanded(string $lane): bool
    {
        $head = trim((string) @shell_exec('git -C ' . escapeshellarg($lane) . ' rev-parse HEAD 2>/dev/null'));
        $branch = trim((string) @shell_exec('git -C ' . escapeshellarg($this->root) . ' rev-parse HEAD 2>/dev/null'));

        if ($head === '' || $branch === '') {
            return false; // Unreadable is not landed. Keeping a lane costs a directory; losing one costs the work.
        }

        return trim((string) @shell_exec('git -C ' . escapeshellarg($this->root) . ' merge-base --is-ancestor ' . escapeshellarg($head) . ' ' . escapeshellarg($branch) . ' && echo landed')) === 'landed';
    }
}
