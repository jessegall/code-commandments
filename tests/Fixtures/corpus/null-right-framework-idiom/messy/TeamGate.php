<?php

namespace App\OptionCorpus\NullRightFrameworkIdiom\Messy;

/** Authorization helper guarding team-scoped actions. */
final class TeamGate
{
    public function hasTeam(User $user): bool
    {
        // `isSome()` is just a renamed `!== null` — ceremony, no safety gained.
        return $user->currentTeam()->isSome();
    }
}
