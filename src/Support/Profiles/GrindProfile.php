<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * Heads-down cadence. Implement the whole plan phase-by-phase with NO judge/test
 * between phases, then reckon once at the end (warnings are still flagged) and
 * push. The only enforcement floor: a pre-push gate blocks pushing unresolved
 * sins, judged across the whole branch (so it survives the intermediate commits).
 *
 * Cadence, not severity: warnings are SHOWN, never blocked. To silence warnings
 * use {@see SinsOnlyProfile} instead.
 */
final class GrindProfile extends Profile
{
    public function name(): string
    {
        return 'grind';
    }

    public function description(): string
    {
        return 'Heads-down — no checks between phases; reckon (judge + tests) once at the end, pre-push gate blocks sins.';
    }

    public function options(): ProfileOptions
    {
        return new ProfileOptions(
            allowWarnings: true,
            scope: JudgeScope::Branch,
            gate: GitGateStage::PrePush,
            briefAgent: true,
            briefing: Briefing::Short,
            perPhaseNudges: false,
            postCommitReset: false,
            prePushReset: true,
            keepGoing: true,
        );
    }
}
