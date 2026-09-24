<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Support\FileTree;

/**
 * Reads judged files (any {@see Language} judge reads) from git — working-tree changes vs HEAD, or files
 * new/changed on the branch vs base. Shared by WorkingTreeChanges, BranchChanges, and
 * HookIO. Not final — a seam for tests to stub the git layer.
 */
class GitFiles
{
    /**
     * How a worktree's `.git` file names the git directory it belongs to.
     */
    private const string GITDIR = 'gitdir:';

    /**
     * The status header that names the commit HEAD stands on.
     */
    private const string HEAD_HEADER = '# branch.oid ';

    /**
     * What that header says in a repository with no commit yet.
     */
    private const string NO_COMMIT = '(initial)';

    /**
     * The folder a repository keeps its LINKED worktrees' git directories in — what tells one from a
     * submodule, whose `.git` file names `modules/<name>` in the same shape.
     */
    private const string WORKTREES = 'worktrees';

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
        $gitdir = self::gitDirOf($root);

        if ($gitdir === null) {
            return false;
        }

        return self::within($gitdir, $project);
    }

    /**
     * The MAIN worktree of the repository $path is in — the one fact every worktree agrees on, and the
     * only honest home for anything belonging to the project rather than to one checkout of it. A
     * worktree is its own git toplevel, so asking for the toplevel gives a different answer inside a
     * lane, and a `cd` then silently moves which file is read. Null outside a repository.
     *
     * Walked, not asked, for the same reason {@see root} is: this now answers where a SESSION's name is
     * read from, so it is paid on every state path a hook builds, and a subprocess there is the most
     * expensive thing a tool call does. Git still answers the setups a walk cannot know about — a bare
     * repo, a `$GIT_DIR` override, a submodule.
     */
    public function projectRoot(string $path): ?string
    {
        $top = $this->root($path);

        if ($top === null) {
            return self::askProjectRoot($path);
        }

        return self::mainWorktreeOf($top) ?? self::askProjectRoot($path);
    }

    /**
     * The main worktree $top belongs to, read off the filesystem. A normal checkout's `.git` is a
     * DIRECTORY, so $top is already the answer; a LINKED worktree's is a file naming
     * `<main>/.git/worktrees/<name>`, three levels below the main worktree. Null for anything else —
     * a submodule names `<super>/.git/modules/<name>`, which is not a worktree of it — so git is asked
     * rather than a wrong answer guessed.
     */
    private static function mainWorktreeOf(string $top): ?string
    {
        if (is_dir($top . '/.git')) {
            return $top;
        }

        $gitdir = self::gitDirOf($top);

        if ($gitdir === null || basename(dirname($gitdir)) !== self::WORKTREES) {
            return null;
        }

        return dirname($gitdir, 3);
    }

    /**
     * The git directory $root's `.git` FILE names — resolved absolute, since a worktree may name it
     * relatively. Null when `.git` is a directory (a normal checkout) or names nothing.
     */
    private static function gitDirOf(string $root): ?string
    {
        $contents = is_file($root . '/.git') ? trim((string) @file_get_contents($root . '/.git')) : '';

        if (! str_starts_with($contents, self::GITDIR)) {
            return null;
        }

        $gitdir = trim(substr($contents, strlen(self::GITDIR)));

        if (! str_starts_with($gitdir, '/')) {
            $gitdir = $root . '/' . $gitdir;
        }

        return realpath($gitdir) ?: $gitdir;
    }

    private static function askProjectRoot(string $path): ?string
    {
        $common = trim((string) @shell_exec('git -C ' . escapeshellarg($path) . ' rev-parse --git-common-dir 2>/dev/null'));

        if ($common === '') {
            return null;
        }

        // A relative answer means we are already standing in the main worktree; an absolute one names it.
        $resolved = str_starts_with($common, '/') ? dirname($common) : self::askGit($path);

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
        return $this->workingTree($root)->changed;
    }

    /**
     * HEAD and the files changed on top of it, from ONE `git status` — a hook that needs both pays for a
     * single process, not one per question. Renames read as the path they now have.
     */
    public function workingTree(string $root): WorkingTree
    {
        $status = (string) @shell_exec('git -C ' . escapeshellarg($root) . ' status --porcelain=v2 --branch --no-ahead-behind --no-renames --untracked-files=all -z 2>/dev/null');
        $head = '';
        $paths = [];

        foreach (explode("\0", $status) as $entry) {
            if (str_starts_with($entry, self::HEAD_HEADER)) {
                $head = substr($entry, strlen(self::HEAD_HEADER));

                continue;
            }

            $paths[] = self::statusPath($entry);
        }

        return new WorkingTree($head === self::NO_COMMIT ? '' : $head, $this->pathSet($root, implode("\n", $paths)));
    }

    /**
     * The path one porcelain-v2 status entry is about: a changed entry spells it after its eight fields, a
     * conflicted one after ten, an untracked one after its mark. A header names no path.
     */
    private static function statusPath(string $entry): string
    {
        return match ($entry[0] ?? '') {
            '1' => explode(' ', $entry, 9)[8],
            'u' => explode(' ', $entry, 11)[10],
            '?' => substr($entry, 2),
            default => '',
        };
    }

    /**
     * The lines of $file that differ from HEAD — every line of a file git does not track, or of a tree
     * that is no repository at all.
     */
    public function changedLines(string $root, string $file): ChangedLines
    {
        $tracked = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' ls-files -- ' . escapeshellarg($file) . ' 2>/dev/null'));

        if ($tracked === '') {
            return ChangedLines::everywhere();
        }

        return ChangedLines::fromDiff((string) @shell_exec('git -C ' . escapeshellarg($root) . ' diff -U0 HEAD -- ' . escapeshellarg($file) . ' 2>/dev/null'));
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
     * Resolve newline-separated repo-relative paths into a set of absolute paths some
     * engine judges (non-judged extensions dropped), so a scoped run narrows to
     * touched source in every language, not PHP alone. A file no scan would reach — under
     * a hidden folder, a dependency tree, build output — is not source, whatever git says.
     *
     * @return array<string, true>
     */
    private function pathSet(string $root, string $lines): array
    {
        $top = (string) realpath($root);
        $set = [];

        foreach (preg_split('/\R/', $lines) ?: [] as $relative) {
            $relative = trim($relative);

            if ($relative === '' || ! Language::judges($relative)) {
                continue;
            }

            $absolute = realpath($root . '/' . $relative);

            if ($absolute !== false && FileTree::reaches($top, $absolute)) {
                $set[$absolute] = true;
            }
        }

        return $set;
    }

}
