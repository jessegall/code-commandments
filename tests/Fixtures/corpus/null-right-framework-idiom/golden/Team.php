<?php

namespace App\OptionCorpus\NullRightFrameworkIdiom\Golden;

/** A team a user can belong to and switch between. */
final readonly class Team
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
