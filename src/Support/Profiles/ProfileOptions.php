<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * The behavioural knobs of a {@see Profile}. The options ARE the behaviour: the
 * {@see ProfileService} derives exactly which git hooks, Claude hooks, briefing,
 * and CLAUDE.md section to install from these flags, and {@see \JesseGall\CodeCommandments\Support\JudgeService}
 * reads them to pick scope and gate severity.
 *
 * Immutable value object (the package idiom — cf. {@see \JesseGall\CodeCommandments\Results\RepentInput}).
 */
final class ProfileOptions
{
    public function __construct(
        /** false => warnings are never emitted, counted, or seen-marked (sins-only). */
        public readonly bool $allowWarnings,
        /** What a bare `judge` looks at, and what the git gate judges against. */
        public readonly JudgeScope $scope,
        /** Where the blocking git gate sits (if anywhere). */
        public readonly GitGateStage $gate,
        /** Master switch for ALL agent awareness (session-start briefing, CLAUDE.md section, drift hook). */
        public readonly bool $briefAgent,
        /** Which briefing body to inject (only read when $briefAgent). */
        public readonly Briefing $briefing,
        /** Install the per-phase nudges: stop-judge + phase-committed + post-commit reminder. */
        public readonly bool $perPhaseNudges,
        /** Install the post-commit hook that clears ordinary absolutions. */
        public readonly bool $postCommitReset,
        /** Install the pre-push hook that clears until-push absolutions. */
        public readonly bool $prePushReset,
        /**
         * Install the keep-going Stop hook: block stopping until the profile's goal
         * is met (grind: no branch sins; phased/sins-only: changes clean; penance:
         * nothing left). Self-completing + capped, so it never hard-traps.
         */
        public readonly bool $keepGoing = false,
    ) {}

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
