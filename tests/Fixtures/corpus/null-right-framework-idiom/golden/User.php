<?php

namespace App\OptionCorpus\NullRightFrameworkIdiom\Golden;

/** App user whose "current team" mirrors the framework's nullable relation idiom. */
final class User
{
    public function __construct(
        private ?Team $currentTeam = null,
    ) {}

    /**
     * Mirror of the idiomatic framework nullable accessor (cf. Jetstream's
     * User::currentTeam()): null simply means "no team selected yet".
     */
    public function currentTeam(): ?Team
    {
        return $this->currentTeam;
    }
}
