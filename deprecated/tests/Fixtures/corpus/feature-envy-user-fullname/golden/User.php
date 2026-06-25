<?php

namespace App\FeatureEnvy\UserFullname;

/**
 * A registered user who owns its own display name and greetings.
 */
final readonly class User
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public Profile $profile,
    ) {}

    public function displayName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function salutation(): string
    {
        return $this->profile->salutation($this->displayName());
    }

    public function credential(): string
    {
        return $this->profile->credential($this->displayName());
    }
}
