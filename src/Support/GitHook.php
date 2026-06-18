<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The git hooks code-commandments installs. The case value is the hook's
 * filename under `.git/hooks/`.
 */
enum GitHook: string
{
    case PreCommit = 'pre-commit';
    case PostCommit = 'post-commit';
    case CommitMsg = 'commit-msg';
    case PrePush = 'pre-push';
}
