<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * Detects files that have been changed in git.
 */
final class GitFileDetector
{
    public function __construct(
        private string $basePath,
    ) {}

    /**
     * Create a detector for the given base path.
     */
    public static function for(string $basePath): self
    {
        return new self($basePath);
    }

    /**
     * Get all files that are new or changed in git, including submodules.
     *
     * @return array<string>
     */
    public function getChangedFiles(): array
    {
        $files = array_merge(
            $this->getChangedFilesIn($this->basePath),
            $this->getSubmoduleChangedFiles(),
        );

        return Pipeline::from($files)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Check if there are any changed files.
     */
    public function hasChanges(): bool
    {
        return ! empty($this->getChangedFiles());
    }

    /**
     * Get only the files staged for commit (the index). This is what a
     * pre-commit hook should judge — unstaged or branch-historical changes
     * are not part of the commit being made.
     *
     * @return array<string>
     */
    public function getStagedFiles(): array
    {
        $git = 'git -C ' . escapeshellarg($this->basePath);

        // ACMR = added, copied, modified, renamed (skip deletions).
        $files = $this->parseGitOutput(
            shell_exec("{$git} diff --name-only --cached --diff-filter=ACMR 2>/dev/null"),
            $this->basePath,
        );

        return Pipeline::from($files)
            ->filter(fn ($file) => is_file($file))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get every file changed since this branch diverged from its base —
     * `merge-base(<base>, HEAD)..HEAD` plus the working tree, index, and
     * untracked files. Unlike {@see self::getChangedFiles()} (diff vs HEAD), this
     * INCLUDES already-committed work, so it survives intermediate phase commits.
     * This is the grind "reckon at the end" scope.
     *
     * @return array<string>
     */
    public function getBranchFiles(): array
    {
        $git = 'git -C ' . escapeshellarg($this->basePath);
        $base = $this->branchBase();

        $committed = $base === null
            ? []
            : $this->parseGitOutput(
                shell_exec("{$git} diff --name-only --diff-filter=ACMR " . escapeshellarg($base) . '...HEAD 2>/dev/null'),
                $this->basePath,
            );

        $files = array_merge($committed, $this->getChangedFilesIn($this->basePath));

        return Pipeline::from($files)
            ->filter(fn ($file) => is_file($file))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Resolve the branch's base revision to diff against: the upstream tracking
     * ref if set, else origin/main, origin/master, main, or master — whichever
     * resolves. Null when none do (e.g. a fresh repo with no base), in which case
     * the caller falls back to the working-tree diff alone.
     */
    private function branchBase(): ?string
    {
        $git = 'git -C ' . escapeshellarg($this->basePath);

        foreach (['@{upstream}', 'origin/main', 'origin/master', 'main', 'master'] as $ref) {
            $hash = trim((string) shell_exec("{$git} rev-parse --verify --quiet " . escapeshellarg($ref) . ' 2>/dev/null'));

            if ($hash === '') {
                continue;
            }

            $base = trim((string) shell_exec("{$git} merge-base " . escapeshellarg($ref) . ' HEAD 2>/dev/null'));

            if ($base !== '') {
                return $base;
            }
        }

        return null;
    }

    /**
     * Get changed files for a specific git directory.
     *
     * @return array<string>
     */
    private function getChangedFilesIn(string $repoPath): array
    {
        $git = 'git -C ' . escapeshellarg($repoPath);

        return array_merge(
            $this->parseGitOutput(shell_exec("{$git} diff --name-only HEAD 2>/dev/null"), $repoPath),
            $this->parseGitOutput(shell_exec("{$git} diff --name-only --cached 2>/dev/null"), $repoPath),
            $this->parseGitOutput(shell_exec("{$git} ls-files --others --exclude-standard 2>/dev/null"), $repoPath),
        );
    }

    /**
     * Get changed files from all git submodules.
     *
     * @return array<string>
     */
    private function getSubmoduleChangedFiles(): array
    {
        $output = shell_exec('git -C ' . escapeshellarg($this->basePath) . ' submodule --quiet foreach "echo \$sm_path" 2>/dev/null');

        if (empty($output)) {
            return [];
        }

        $files = [];

        foreach (explode(T_String::NEWLINE, trim($output)) as $submodulePath) {
            if (T_String::isEmpty($submodulePath)) {
                continue;
            }

            $absolutePath = $this->basePath . '/' . $submodulePath;

            if (is_dir($absolutePath)) {
                $files = array_merge($files, $this->getChangedFilesIn($absolutePath));
            }
        }

        return $files;
    }

    /**
     * Parse git command output into absolute file paths.
     *
     * @return array<string>
     */
    private function parseGitOutput(?string $output, string $repoPath): array
    {
        if (empty($output)) {
            return [];
        }

        return Pipeline::from(explode(T_String::NEWLINE, trim($output)))
            ->filter(fn ($file) => T_String::isNotEmpty($file))
            ->map(fn ($file) => $repoPath . '/' . $file)
            ->toArray();
    }
}
