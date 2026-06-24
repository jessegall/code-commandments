<?php

namespace App\OptionCorpus\NullRightFrameworkIdiom\Golden;

/** View composer that renders the current team label in the top bar. */
final class TopBarComposer
{
    public function teamLabel(User $user): string
    {
        // One clean coalesce — exactly the framework's own `?->name ?? '—'` idiom.
        return $user->currentTeam()?->name ?? '—';
    }
}
