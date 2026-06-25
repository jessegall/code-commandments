<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * Single-prophet repent mode. The whole job is to drive ONE prophet's findings to
 * zero across the codebase — not the whole backlog (that is penance), not a feature.
 * On switching, the agent must ASK the user WHICH PROPHET, then work only that one:
 * auto-fix the [AUTO-FIXABLE] ones with `repent --prophet=<NAME>`, fix the rest by
 * hand, and `judge --prophet=<NAME>` until clean. No git gates and no keep-going
 * loop — the focus is the contract, so the agent stays on the one prophet without
 * the whole-codebase machinery thrashing it.
 */
final class RepentrProfile extends Profile
{
    public function name(): string
    {
        return 'repentr';
    }

    public function description(): string
    {
        return 'Repent a SINGLE prophet — ask WHICH prophet, then drive only its findings to zero (no gate, no whole-codebase loop).';
    }

    public function options(): ProfileOptions
    {
        return new ProfileOptions(
            allowWarnings: true,
            scope: JudgeScope::None,
            // No keep-going loop and no git gate: the briefing is the contract. The
            // agent narrows every command to the chosen prophet, so a whole-codebase
            // gate would only thrash the focused pass.
            behaviour: new ProfileBehaviour(judge: Phase::Never, test: Phase::Never, ask: Inquiry::OnDecisions),
            briefAgent: true,
            briefing: Briefing::Repentr,
            postCommitReset: false,
            prePushReset: false,
        );
    }
}
