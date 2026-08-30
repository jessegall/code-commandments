<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * A {@see GitFiles} whose worktree root, HEAD, branch and linked worktrees are fixed — so a hook or a
 * command can be exercised without a real repository. Only the reads those need are overridden.
 */
final class FakeGit extends GitFiles
{
    /**
     * @param  list<string>  $worktrees  the linked checkouts this repository has, main one excluded
     */
    public function __construct(
        private readonly string $root,
        public string $head = 'sha',
        public string $branch = 'plan/x',
        public array $worktrees = [],
    ) {}

    public function root(string $path): ?string
    {
        return $this->root;
    }

    public function worktrees(string $root): array
    {
        return $this->worktrees;
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
