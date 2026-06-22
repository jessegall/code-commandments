<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * Where a profile's blocking git gate sits (if anywhere).
 *
 * - {@see GitGateStage::PreCommit}: block the commit on staged findings (phased).
 * - {@see GitGateStage::PrePush}: block the push on branch-scoped sins (grind).
 * - {@see GitGateStage::None}: no blocking gate (disabled).
 */
enum GitGateStage: string
{
    /** No blocking gate (disabled). */
    case None = 'none';

    /** Block the commit on staged findings (phased). */
    case PreCommit = 'pre-commit';

    /** Block the push on branch-scoped sins (grind). */
    case PrePush = 'pre-push';
}
