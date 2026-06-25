<?php

namespace App\FeatureEnvy\UserFullname;

/**
 * A customer's profile details: their honorific title and job role.
 */
final class Profile
{
    public function __construct(
        private string $title,
        private string $jobRole,
    ) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getJobRole(): string
    {
        return $this->jobRole;
    }
}
