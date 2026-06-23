<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The git hooks code-commandments installs. The case value is the hook's
 * filename under `.git/hooks/`.
 */
enum GitHook: string
{
    /** Runs before a commit is recorded — hosts the gate that blocks commits with sins (judging staged files). */
    case PreCommit = 'pre-commit';

    /** Runs after a commit is recorded — hosts the reset that clears ordinary absolutions so nothing stays hidden across commits. */
    case PostCommit = 'post-commit';

    /** Runs against the commit message file — hosts the message guard. */
    case CommitMsg = 'commit-msg';

    /** Runs before a push — hosts the push-time gate and clears sticky `--until-push` absolutions so they don't outlive the push. */
    case PrePush = 'pre-push';
}
