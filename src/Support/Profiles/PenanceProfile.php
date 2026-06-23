<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * Remediation mode. The whole job is to drive the EXISTING backlog of sins and
 * warnings to zero — not build a feature. Designed so the gate can't thrash a
 * cleanup pass: there is NO pre-commit gate (commit progress freely; fixing a
 * file no longer re-arms a blocker on it), only a pre-push gate that blocks
 * pushing while sins remain. Keep-going holds the session open until `judge` is
 * righteous, and `judge --plan` lays out the fix order (root causes first).
 */
final class PenanceProfile extends Profile
{
    public function name(): string
    {
        return 'penance';
    }

    public function description(): string
    {
        return 'Cleanup — drive the whole backlog to zero (judge --plan, root-cause first); no commit gate, push blocked while sins remain.';
    }

    public function options(): ProfileOptions
    {
        return new ProfileOptions(
            allowWarnings: true,
            scope: JudgeScope::None,
            gate: GitGateStage::PrePush,
            briefAgent: true,
            briefing: Briefing::Full,
            perPhaseNudges: false,
            postCommitReset: false,
            prePushReset: true,
            keepGoing: true,
        );
    }
}
