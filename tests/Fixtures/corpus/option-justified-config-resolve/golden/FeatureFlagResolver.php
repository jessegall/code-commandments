<?php

namespace App\OptionCorpus\OptionJustifiedConfigResolve;

/** Resolves a feature flag through the user -> team -> system fallback chain as an Option. */
final class FeatureFlagResolver
{
    public function __construct(
        private readonly UserPreferenceStore $userPrefs,
        private readonly TeamPolicyStore $teamPolicies,
        private readonly SystemDefaults $systemDefaults,
    ) {}

    /**
     * The maybe-value of ONE source. Each layer may or may not have an opinion
     * on this flag; "absent" means "this layer defers to the next one".
     */
    public function resolve(int $userId, int $teamId, string $flag): Option
    {
        return $this->userLevel($userId, $flag)
            ->orElse(fn () => $this->teamLevel($teamId, $flag))
            ->orElse(fn () => $this->systemLevel($flag));
    }

    private function userLevel(int $userId, string $flag): Option
    {
        return Option::fromNullable($this->userPrefs->flagFor($userId, $flag));
    }

    private function teamLevel(int $teamId, string $flag): Option
    {
        return Option::fromNullable($this->teamPolicies->flagFor($teamId, $flag));
    }

    private function systemLevel(string $flag): Option
    {
        return Option::fromNullable($this->systemDefaults->flag($flag));
    }
}
