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

        foreach (explode("\n", trim($output)) as $submodulePath) {
            if ($submodulePath === '') {
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

        return Pipeline::from(explode("\n", trim($output)))
            ->filter(fn ($file) => $file !== '')
            ->map(fn ($file) => $repoPath . '/' . $file)
            ->toArray();
    }
}
