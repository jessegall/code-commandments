<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * Heads-down cadence. Implement the whole plan phase-by-phase with NO judge/test
 * between phases, then reckon once at the end and push. The enforcement floor is
 * a pre-push gate that blocks pushing unresolved findings — sins AND warnings —
 * judged across the whole branch (so it survives the intermediate commits). The
 * reckoning before the push must be clean: every warning is fixed or absolved
 * with a reason, just like a sin.
 *
 * Cadence, not severity: warnings still surface (to silence them use
 * {@see SinsOnlyProfile}); the grind just defers the reckoning to the end.
 */
final class GrindProfile extends Profile
{
    public function name(): string
    {
        return 'grind';
    }

    public function description(): string
    {
        return 'Heads-down — no checks between phases; reckon (judge + tests) once at the end, pre-push gate blocks sins + warnings.';
    }

    public function options(): ProfileOptions
    {
        return new ProfileOptions(
            allowWarnings: true,
            scope: JudgeScope::Branch,
            // Heads-down: judge AND tests deferred to ONE reckon at the end (the
            // pre-push gate) — never between phases.
            behaviour: new ProfileBehaviour(judge: Phase::AtEnd, test: Phase::AtEnd, ask: Inquiry::WhenBlocked),
            briefAgent: true,
            briefing: Briefing::Short,
            postCommitReset: false,
            prePushReset: true,
        );
    }
}
