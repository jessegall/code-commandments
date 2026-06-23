<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * The classic face-by-face discipline (the package's historical default): a
 * pre-commit gate blocks staged sins AND warnings, per-phase nudges drive
 * fix-as-you-go, absolutions reset after each commit, and the full scripture
 * briefs the agent.
 */
final class PhasedProfile extends Profile
{
    public function name(): string
    {
        return 'phased';
    }

    public function description(): string
    {
        return 'Face-by-face — pre-commit gate blocks staged sins + warnings, per-phase nudges, full briefing.';
    }

    public function options(): ProfileOptions
    {
        return new ProfileOptions(
            allowWarnings: true,
            scope: JudgeScope::Staged,
            gate: GitGateStage::PreCommit,
            briefAgent: true,
            briefing: Briefing::Full,
            perPhaseNudges: true,
            postCommitReset: true,
            prePushReset: true,
            keepGoing: true,
        );
    }
}
