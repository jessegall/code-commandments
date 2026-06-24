<?php

namespace App\OptionCorpus\NullRightFrameworkIdiom\Golden;

/** Authorization helper guarding team-scoped actions. */
final class TeamGate
{
    public function hasTeam(User $user): bool
    {
        // Absence is just "not set" — a plain null check reads at a glance.
        return $user->currentTeam() !== null;
    }
}
