<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * Reads sets of judged files (`.php`/`.vue`, one per engine) out of git:
 * working-tree changes vs HEAD, or everything new/changed on the current branch
 * vs a base ref. Shared by the {@see WorkingTreeChanges} and {@see BranchChanges}
 * scopes.
 */
final class GitFiles
{
    /**
     * The git toplevel containing $path, or null when $path is not in a repository.
     */
    public function root(string $path): ?string
    {
        $dir = is_dir($path) ? $path : dirname($path);
        $root = trim((string) @shell_exec('git -C ' . escapeshellarg($dir) . ' rev-parse --show-toplevel 2>/dev/null'));

        return $root === '' ? null : $root;
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

    /** The extensions judge parses — one per engine (`.php` backend, `.vue` frontend). */
    private const array JUDGED = ['.php', '.vue'];

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
