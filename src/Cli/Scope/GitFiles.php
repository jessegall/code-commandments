<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * Reads judged files (`.php`/`.vue`) from git — working-tree changes vs HEAD, or files
 * new/changed on the branch vs base. Shared by WorkingTreeChanges, BranchChanges, and
 * HookIO. Not final — a seam for tests to stub the git layer.
 */
class GitFiles
{
    /**
     * The extensions judge parses — one per engine (`.php` backend, `.vue` frontend).
     */
    private const array JUDGED = ['.php', '.vue'];

    /**
     * How a worktree's `.git` file names the git directory it belongs to.
     */
    private const string GITDIR = 'gitdir:';

    /**
     * The git toplevel containing $path, or null when $path is not in a repository.
     */
    public function root(string $path): ?string
    {
        $dir = is_dir($path) ? $path : dirname($path);

        // Walked, not asked. "Which directory holds this one's `.git`" is a filesystem question, and
        // a subprocess to answer it is the single most expensive thing a hook does — paid on EVERY
        // tool call, for a string that never changes. Git still answers for the setups a walk cannot
        // know about (a bare repo, a `$GIT_DIR` override), which is what the fall-through is for.
        return self::walkUp($dir) ?? self::askGit($dir);
    }

    /**
     * The nearest ancestor of $dir holding a `.git` — a DIRECTORY in a normal clone, a FILE in a
     * worktree or submodule, so both count. Null when the walk reaches the filesystem root.
     */
    private static function walkUp(string $dir): ?string
    {
        $dir = realpath($dir) ?: $dir;

        while (true) {
            if (file_exists($dir . '/.git')) {
                return $dir;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                return null;
            }

            $dir = $parent;
        }
    }

    private static function askGit(string $dir): ?string
    {
        $root = trim((string) @shell_exec('git -C ' . escapeshellarg($dir) . ' rev-parse --show-toplevel 2>/dev/null'));

        return $root === '' ? null : $root;
    }

    /**
     * Is $root part of the SAME repository as $project — the checkout itself, a directory inside it, or a
     * worktree whose git directory lives in it? What tells a worktree of this project, whose own root is
     * the right scope, from an unrelated repository a shell merely stepped into.
     */
    public function belongsTo(string $root, string $project): bool
    {
        $root = realpath($root) ?: $root;
        $project = realpath($project) ?: $project;

        if (self::within($root, $project)) {
            return true;
        }

        // A worktree's `.git` is a FILE naming the git directory it belongs to; a checkout of another
        // project names one somewhere else entirely, which is exactly the case being refused.
        $contents = is_file($root . '/.git') ? trim((string) @file_get_contents($root . '/.git')) : '';

        if (! str_starts_with($contents, self::GITDIR)) {
            return false;
        }

        $gitdir = trim(substr($contents, strlen(self::GITDIR)));

        if (! str_starts_with($gitdir, '/')) {
            $gitdir = $root . '/' . $gitdir; // a worktree may name its git directory relatively
        }

        return self::within(realpath($gitdir) ?: $gitdir, $project);
    }

    /**
     * The MAIN worktree of the repository $path is in — the one fact every worktree agrees on, and the
     * only honest home for anything belonging to the project rather than to one checkout of it. A
     * worktree is its own git toplevel, so asking for the toplevel gives a different answer inside a
     * lane, and a `cd` then silently moves which file is read. Null outside a repository.
     */
    public function projectRoot(string $path): ?string
    {
        $common = trim((string) @shell_exec('git -C ' . escapeshellarg($path) . ' rev-parse --git-common-dir 2>/dev/null'));

        if ($common === '') {
            return null;
        }

        // A relative answer means we are already standing in the main worktree; an absolute one names it.
        $resolved = str_starts_with($common, '/') ? dirname($common) : $this->root($path);

        return $resolved === false ? null : $resolved;
    }

    /**
     * Every worktree of this repository EXCEPT the main one — the places a file may have been written
     * that nothing reads any more.
     *
     * @return list<string>
     */
    public function worktrees(string $root): array
    {
        $listing = (string) @shell_exec('git -C ' . escapeshellarg($root) . ' worktree list --porcelain 2>/dev/null');
        $found = [];

        foreach (explode("\n", $listing) as $line) {
            if (! str_starts_with($line, 'worktree ')) {
                continue;
            }

            $path = substr($line, strlen('worktree '));

            if (realpath($path) !== realpath($root)) {
                $found[] = $path;
            }
        }

        return $found;
    }

    /**
     * Is $path $parent or somewhere beneath it?
     */
    private static function within(string $path, string $parent): bool
    {
        return $path === $parent || str_starts_with($path, rtrim($parent, '/') . '/');
    }

    /**
     * The current HEAD commit sha, or '' when there is none (a repo with no commits).
     * A stable per-commit key: it changes exactly when a commit lands.
     */
    public function head(string $root): string
    {
        return trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null'));
    }

    /**
     * The current branch name (e.g. `main`, `plan/foo`), or '' in a detached HEAD / non-repo.
     * Used to tell when a plan branch has been merged back to its base.
     */
    public function currentBranch(string $root): string
    {
        return trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --abbrev-ref HEAD 2>/dev/null'));
    }

    /**
     * Files changed or created in the working tree: tracked changes vs HEAD plus
     * untracked files (deletions excluded). Empty set in a clean repo.
     *
     * @return array<string, true>
     */
    public function changedVsHead(string $root): array
    {
        $tracked = (string) @shell_exec('git -C ' . escapeshellarg($root) . ' diff --name-only --diff-filter=d HEAD 2>/dev/null');
        $untracked = (string) @shell_exec('git -C ' . escapeshellarg($root) . ' ls-files --others --exclude-standard 2>/dev/null');

        return $this->pathSet($root, $tracked . "\n" . $untracked);
    }

    /**
     * Files new or changed on the current branch vs $base — everything that differs
     * from the merge-base down to the working tree (committed AND uncommitted) plus
     * untracked files. Uses the merge-base, so it needs no separate worktree.
     * Returns null when $base is not a known ref.
     *
     * @return array<string, true>|null
     */
    public function changedVsBranch(string $root, string $base): ?array
    {
        $mergeBase = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' merge-base ' . escapeshellarg($base) . ' HEAD 2>/dev/null'));

        if ($mergeBase === '') {
            return null;
        }

        $tracked = (string) @shell_exec('git -C ' . escapeshellarg($root) . ' diff --name-only --diff-filter=d ' . escapeshellarg($mergeBase) . ' 2>/dev/null');
        $untracked = (string) @shell_exec('git -C ' . escapeshellarg($root) . ' ls-files --others --exclude-standard 2>/dev/null');

        return $this->pathSet($root, $tracked . "\n" . $untracked);
    }

    /**
     * Resolve newline-separated repo-relative paths into a set of absolute paths the
     * two engines judge (non-judged extensions dropped), so a scoped run narrows to
     * touched source across BOTH front-ends, not PHP alone.
     *
     * @return array<string, true>
     */
    private function pathSet(string $root, string $lines): array
    {
        $set = [];

        foreach (preg_split('/\R/', $lines) ?: [] as $relative) {
            $relative = trim($relative);

            if ($relative === '' || ! $this->isJudged($relative)) {
                continue;
            }

            $absolute = realpath($root . '/' . $relative);

            if ($absolute !== false) {
                $set[$absolute] = true;
            }
        }

        return $set;
    }

    /**
     * Does $relative name a file one of the engines judges?
     */
    private function isJudged(string $relative): bool
    {
        foreach (self::JUDGED as $extension) {
            if (str_ends_with($relative, $extension)) {
                return true;
            }
        }

        return false;
    }
}
