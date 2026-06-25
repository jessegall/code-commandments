<?php

namespace App\FeatureEnvy\UserFullname;

/**
 * A customer's profile details: their honorific title and job role.
 */
final readonly class Profile
{
    public function __construct(
        public string $title,
        public JobRole $jobRole,
    ) {}

    public function salutation(string $name): string
    {
        return $this->title.' '.$name;
    }

    public function credential(string $name): string
    {
        return $this->title.' '.$name.', '.$this->jobRole->label();
    }
}
