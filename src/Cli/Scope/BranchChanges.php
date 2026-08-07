<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * The `--branch[=BASE]` scope: report only on files new or changed on the current
 * branch vs BASE (default `main`) — committed AND uncommitted — via the merge-base.
 */
final class BranchChanges implements ChangeScope
{
    public function __construct(
        private readonly string $base,
        private readonly GitFiles $git = new GitFiles,
    ) {}

    public function restrictTo(string $path): ?array
    {
        $root = $this->git->root($path);

        if ($root === null) {
            throw ScopeUnavailable::notAGitRepository($path);
        }

        $changed = $this->git->changedVsBranch($root, $this->base);

        if ($changed === null) {
            throw ScopeUnavailable::baseRefMissing($this->base, $path);
        }

        return $changed;
    }
}
