<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * A {@see GitFiles} whose worktree root, HEAD, and branch are fixed — so a hook can be exercised
 * without a real repository. Only the reads the plan hook uses are overridden.
 */
final class FakeGit extends GitFiles
{
    public function __construct(
        private readonly string $root,
        public string $head = 'sha',
        public string $branch = 'plan/x',
    ) {}

    public function root(string $path): ?string
    {
        return $this->root;
    }

    public function head(string $root): string
    {
        return $this->head;
    }

    public function currentBranch(string $root): string
    {
        return $this->branch;
    }
}
