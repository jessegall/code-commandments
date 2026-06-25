<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * The behavioural knobs of a {@see Profile}. The options ARE the behaviour: the
 * {@see ProfileService} derives exactly which git hooks, Claude hooks, briefing,
 * and CLAUDE.md section to install from these, and {@see \JesseGall\CodeCommandments\Support\JudgeService}
 * reads them to pick scope and gate severity.
 *
 * The cadence lives in {@see ProfileBehaviour} ({@see self::$behaviour}) — WHEN
 * the profile judges and tests — and the git gate placement + per-phase nudge
 * are DERIVED from it (so a profile declares its cadence once and the gate, the
 * nudge, and the generated Stop hook all follow). The Stop script itself is
 * GENERATED from the behaviour by {@see StopHookBuilder} — the package ships no
 * per-profile Stop stub.
 *
 * Immutable value object (the package idiom — cf. {@see \JesseGall\CodeCommandments\Results\RepentInput}).
 */
final class ProfileOptions
{
    /** Where the blocking git gate sits — DERIVED from {@see $behaviour}. */
    public readonly GitGateStage $gate;

    /** Whether the per-phase (per-commit) nudge installs — DERIVED from {@see $behaviour}. */
    public readonly bool $perPhaseNudges;

    public function __construct(
        /** false => warnings are never emitted, counted, or seen-marked (sins-only). */
        public readonly bool $allowWarnings,
        /** What a bare `judge` looks at, and what the git gate judges against. */
        public readonly JudgeScope $scope,
        /** WHEN the profile judges + tests — the cadence the gate/nudge/Stop hook derive from. */
        public readonly ProfileBehaviour $behaviour,
        /** Master switch for ALL agent awareness (session-start briefing, CLAUDE.md section, drift hook). */
        public readonly bool $briefAgent,
        /** Which briefing body to inject (only read when $briefAgent). */
        public readonly Briefing $briefing,
        /** Install the post-commit hook that clears ordinary absolutions. */
        public readonly bool $postCommitReset,
        /** Install the pre-push hook that clears until-push absolutions. */
        public readonly bool $prePushReset,
    ) {
        $this->gate = $behaviour->gate();
        $this->perPhaseNudges = $behaviour->nudgesEachPhase();
    }

    /** Whether this profile installs a Stop (keep-going) hook — disabled does not. */
    public function hasStopHook(): bool
    {
        return $this->behaviour->hasStopHook();
    }

    /**
     * The blocking severity is DERIVED, not a separate flag: wherever a profile
     * shows warnings AND has a blocking gate, that gate blocks on warnings too —
     * they must be fixed or absolved before it passes. So the staged pre-commit
     * gate (phased) blocks warnings at the commit, and the branch/full pre-push
     * gate (grind, penance) blocks them at the push (reckon-at-the-end). When
     * warnings are suppressed (sins-only) or there is no gate (disabled), there
     * is nothing to gate on.
     */
    public function gateBlocksOnWarnings(): bool
    {
        return $this->allowWarnings && ! GitGateStage::None->equals($this->gate);
    }
}
