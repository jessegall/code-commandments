<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * The `--changes` scope: report only on files changed or created in the working tree
 * (git diff vs HEAD + untracked).
 */
final class WorkingTreeChanges implements ChangeScope
{
    public function __construct(private readonly GitFiles $git = new GitFiles) {}

    public function restrictTo(string $path): ?array
    {
        $root = $this->git->root($path);

        if ($root === null) {
            throw new ScopeUnavailable("Not a git repository (or git unavailable): {$path}");
        }

        return $this->git->changedVsHead($root);
    }
}
