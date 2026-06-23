<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * WHEN a profile judges and tests — the cadence the rest of the profile's
 * machinery is DERIVED from, so it can never drift:
 *  - the git gate placement ({@see self::gate()}) — judge each phase ⇒ block at
 *    commit; judge at the end / until clean ⇒ block at push; never ⇒ no gate;
 *  - whether the per-phase nudge installs ({@see self::nudgesEachPhase()});
 *  - whether the Stop hook itself judges in its keep-going loop
 *    ({@see self::stopHookJudges()}), and over what scope;
 *  - the agent-facing wording in the generated Stop hook (judge/test deferred to
 *    the end vs run each phase).
 *
 * Tests are AGENT GUIDANCE, not something the package executes — `test` only
 * shapes the message + (future) nudge, never shells out to a test runner.
 *
 * Immutable value object (the package idiom).
 */
final class ProfileBehaviour
{
    public function __construct(
        /** When findings are judged. Drives the gate, the nudge, and the Stop hook. */
        public readonly Phase $judge,
        /** When the agent is told to run the test suite (guidance only). */
        public readonly Phase $test,
        /** How readily the agent interrupts the user to ask (guidance only). */
        public readonly Inquiry $ask = Inquiry::WhenBlocked,
    ) {}

    /**
     * The one-line autonomy guidance the keep-going hook shows the agent —
     * derived from {@see $ask}. Fires during the implementation loop (the Stop
     * hook runs only after a plan is approved), so it is implementation-scoped by
     * construction.
     */
    public function askGuidance(): string
    {
        return match ($this->ask) {
            Inquiry::Never => 'Do NOT pause to ask the user — work around obstacles and keep going to the end.',
            Inquiry::WhenBlocked => 'Release the loop ONLY for a genuine blocker (a decision only the user can make, information you cannot find or infer, or an unrecoverable failure): sh .claude/hooks/plan-release.sh "<reason>".',
            Inquiry::OnDecisions => 'Keep going, but PAUSE to ask the user before a consequential or ambiguous decision (an irreversible action, a real trade-off, or several valid approaches) — and for any genuine blocker: sh .claude/hooks/plan-release.sh "<reason>".',
            Inquiry::Freely => 'Ask the user whenever a question would clarify or de-risk the work.',
        };
    }

    /**
     * The autonomy guidance for the session-start briefing — derived from
     * {@see $ask}, and explicitly scoped to DURING IMPLEMENTATION: in plan mode
     * (before the plan is approved) the agent always asks whatever it needs.
     */
    public function autonomyBriefing(): string
    {
        $core = match ($this->ask) {
            Inquiry::Never => 'do NOT pause to ask — work around obstacles and drive to the end',
            Inquiry::WhenBlocked => 'do NOT stop to ask the user to confirm or re-approve — the plan you agreed on IS the authorization; execute it end to end. Only stop for a genuine blocker the plan does not cover (a decision only the user can make, information you cannot find, or an unrecoverable failure)',
            Inquiry::OnDecisions => 'keep going, but pause to ask before a consequential or ambiguous decision (an irreversible action, a real trade-off, or several valid approaches)',
            Inquiry::Freely => 'ask the user whenever a question would clarify or de-risk the work',
        };

        return "Autonomy — DURING IMPLEMENTATION, {$core}. (This applies only once implementing; in PLAN MODE, before the plan is approved, ask whatever you need.)";
    }

    /** The test cadence line for the briefing — derived from {@see $test}. */
    public function testBriefing(): ?string
    {
        return match ($this->test) {
            Phase::AtEnd => 'Tests: run the full suite ONCE before pushing — not between phases.',
            Phase::EachPhase => 'Tests: run the relevant tests each phase, as you go.',
            Phase::UntilClean => 'Tests: keep running the suite until it is green.',
            Phase::Never => null,
        };
    }

    /**
     * The blocking git gate placement DERIVED from the judge cadence: judge each
     * phase ⇒ block at the commit; defer to the end / cleanup ⇒ block at the
     * push; never judge ⇒ no gate.
     */
    public function gate(): GitGateStage
    {
        return match ($this->judge) {
            Phase::EachPhase => GitGateStage::PreCommit,
            Phase::AtEnd, Phase::UntilClean => GitGateStage::PrePush,
            Phase::Never => GitGateStage::None,
        };
    }

    /** Whether the per-phase (per-commit) judge nudge installs. */
    public function nudgesEachPhase(): bool
    {
        return $this->judge === Phase::EachPhase;
    }

    /** Whether there is a Stop (keep-going) hook at all — disabled has none. */
    public function hasStopHook(): bool
    {
        return $this->judge !== Phase::Never;
    }

    /**
     * Whether the Stop hook itself judges in its keep-going loop. AtEnd does NOT
     * (the reckon is the pre-push gate) — that is the grind contract (#197).
     * EachPhase judges the current changes; UntilClean judges the whole codebase.
     */
    public function stopHookJudges(): bool
    {
        return $this->judge === Phase::EachPhase || $this->judge === Phase::UntilClean;
    }
}
