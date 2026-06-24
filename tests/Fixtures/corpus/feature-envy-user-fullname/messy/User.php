<?php

namespace App\FeatureEnvy\UserFullname;

/**
 * A registered user with a name and an attached profile.
 */
final class User
{
    public function __construct(
        private string $firstName,
        private string $lastName,
        private Profile $profile,
    ) {}

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }
}
