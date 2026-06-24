<?php

namespace App\OptionCorpus\NullRightFrameworkIdiom\Messy;

/** View composer that renders the current team label in the top bar. */
final class TopBarComposer
{
    public function teamLabel(User $user): string
    {
        // The Option buys nothing: built, then immediately collapsed back to a
        // value-or-null and coalesced — the exact thing `?->name ?? '—'` did.
        return $user->currentTeam()
            ->map(fn (Team $team) => $team->name)
            ->getOrElse('—');
    }
}
