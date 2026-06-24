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
        return 'Face-by-face — pre-commit gate blocks staged sins + admonitions, per-phase nudges, full briefing.';
    }

    public function options(): ProfileOptions
    {
        return new ProfileOptions(
            allowWarnings: true,
            scope: JudgeScope::Staged,
            // Face-by-face: judge + test each phase; the gate blocks at the commit.
            behaviour: new ProfileBehaviour(judge: Phase::EachPhase, test: Phase::EachPhase, ask: Inquiry::OnDecisions),
            briefAgent: true,
            briefing: Briefing::Full,
            postCommitReset: true,
            prePushReset: true,
        );
    }
}
