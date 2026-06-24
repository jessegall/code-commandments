<?php

namespace App\OptionCorpus\OptionJustifiedConfigResolve;

/** Per-user overrides — returns null when the user has not set this flag. */
final class UserPreferenceStore
{
    public function flagFor(int $userId, string $flag): ?bool
    {
        return null;
    }
}

/** Per-team policy — returns null when the team has no policy for this flag. */
final class TeamPolicyStore
{
    public function flagFor(int $teamId, string $flag): ?bool
    {
        return null;
    }
}

/** System-wide defaults — returns null when the flag is genuinely unknown. */
final class SystemDefaults
{
    public function flag(string $flag): ?bool
    {
        return null;
    }
}

/** Thrown when a flag cannot be resolved at any layer. */
final class UnknownFeatureFlag extends \RuntimeException {}
