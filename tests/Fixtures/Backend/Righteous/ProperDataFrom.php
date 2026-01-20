<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}

    // Righteous: No custom fromModel - use Spatie's built-in from() method
    // Usage: UserData::from($user)
}
