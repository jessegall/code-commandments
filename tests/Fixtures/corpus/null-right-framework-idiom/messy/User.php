<?php

namespace App\OptionCorpus\NullRightFrameworkIdiom\Messy;

use JesseGall\PhpTypes\Option;

/** App user whose "current team" is needlessly wrapped in an Option. */
final class User
{
    public function __construct(
        private ?Team $currentTeam = null,
    ) {}

    /**
     * Over-engineered: wraps the idiomatic nullable relation in an Option that
     * every caller immediately unwraps straight back to a value-or-null.
     *
     * @return Option<Team>
     */
    public function currentTeam(): Option
    {
        return Option::fromNullable($this->currentTeam);
    }
}
