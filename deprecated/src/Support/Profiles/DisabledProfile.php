<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * The default. The package is dormant: no git hooks, no gate, no agent briefing,
 * no CLAUDE.md section. The agent is unaware code-commandments even judges.
 */
final class DisabledProfile extends Profile
{
    public function name(): string
    {
        return 'disabled';
    }

    public function description(): string
    {
        return 'Dormant — no hooks, no gate, no briefing. The agent is unaware the package judges.';
    }

    public function options(): ProfileOptions
    {
        return new ProfileOptions(
            allowWarnings: true,
            scope: JudgeScope::None,
            // Dormant: never judges or tests, no gate, no Stop hook.
            behaviour: new ProfileBehaviour(judge: Phase::Never, test: Phase::Never),
            briefAgent: false,
            briefing: Briefing::Full,
            postCommitReset: false,
            prePushReset: false,
        );
    }
}
