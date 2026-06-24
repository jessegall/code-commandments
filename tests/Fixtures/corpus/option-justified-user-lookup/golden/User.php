<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Golden;

/** A user record resolved from the directory. */
final readonly class User
{
    public function __construct(
        public string $id,
        public string $email,
        public bool $active,
    ) {}
}
