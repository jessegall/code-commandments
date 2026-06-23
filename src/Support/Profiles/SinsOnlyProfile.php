<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * Like {@see PhasedProfile} but warnings are silenced everywhere — never emitted,
 * counted, or seen-marked. Only sins surface and gate. Severity, not cadence:
 * the per-phase rhythm is unchanged.
 */
final class SinsOnlyProfile extends Profile
{
    public function name(): string
    {
        return 'sins-only';
    }

    public function description(): string
    {
        return 'Sins only — warnings are never emitted; pre-commit gate blocks sins, per-phase nudges, full briefing.';
    }

    public function options(): ProfileOptions
    {
        return new ProfileOptions(
            allowWarnings: false,
            scope: JudgeScope::Staged,
            // Phased cadence (judge + test each phase) — warnings suppressed, so
            // only sins surface and gate.
            behaviour: new ProfileBehaviour(judge: Phase::EachPhase, test: Phase::EachPhase),
            briefAgent: true,
            briefing: Briefing::Full,
            postCommitReset: true,
            prePushReset: true,
        );
    }
}
