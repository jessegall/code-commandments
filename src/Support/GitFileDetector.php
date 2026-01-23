<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

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
     * Get all files that are new or changed in git.
     *
     * @return array<string>
     */
    public function getChangedFiles(): array
    {
        return Pipeline::from([])
            ->pipe(fn () => array_merge(
                $this->getModifiedFiles(),
                $this->getStagedFiles(),
                $this->getUntrackedFiles(),
            ))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get modified and unstaged files.
     *
     * @return array<string>
     */
    public function getModifiedFiles(): array
    {
        return $this->parseGitOutput(
            shell_exec('git diff --name-only HEAD 2>/dev/null')
        );
    }

    /**
     * Get staged files.
     *
     * @return array<string>
     */
    public function getStagedFiles(): array
    {
        return $this->parseGitOutput(
            shell_exec('git diff --name-only --cached 2>/dev/null')
        );
    }

    /**
     * Get untracked files.
     *
     * @return array<string>
     */
    public function getUntrackedFiles(): array
    {
        return $this->parseGitOutput(
            shell_exec('git ls-files --others --exclude-standard 2>/dev/null')
        );
    }

    /**
     * Check if there are any changed files.
     */
    public function hasChanges(): bool
    {
        return ! empty($this->getChangedFiles());
    }

    /**
     * Parse git command output into file paths.
     *
     * @return array<string>
     */
    private function parseGitOutput(?string $output): array
    {
        if (empty($output)) {
            return [];
        }

        return Pipeline::from(explode("\n", trim($output)))
            ->filter(fn ($file) => $file !== '')
            ->map(fn ($file) => $this->basePath.'/'.$file)
            ->toArray();
    }
}
