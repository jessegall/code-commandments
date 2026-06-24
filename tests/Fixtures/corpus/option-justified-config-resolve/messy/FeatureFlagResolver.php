<?php

namespace App\OptionCorpus\OptionJustifiedConfigResolve;

/** Resolves a feature flag through the user -> team -> system fallback chain as a nullable bool. */
final class FeatureFlagResolver
{
    public function __construct(
        private readonly UserPreferenceStore $userPrefs,
        private readonly TeamPolicyStore $teamPolicies,
        private readonly SystemDefaults $systemDefaults,
    ) {}

    /**
     * Returns the resolved flag, or null when no layer has an opinion.
     *
     * The `??` ladder collapses three DISTINCT maybe-values into one bare
     * nullable — and crucially, true/false/null all collide here: a layer that
     * explicitly says `false` is indistinguishable from "absent", so `?? next`
     * wrongly falls through a deliberate `false` to the next layer.
     */
    public function resolve(int $userId, int $teamId, string $flag): ?bool
    {
        return $this->userPrefs->flagFor($userId, $flag)
            ?? $this->teamPolicies->flagFor($teamId, $flag)
            ?? $this->systemDefaults->flag($flag);
    }
}
