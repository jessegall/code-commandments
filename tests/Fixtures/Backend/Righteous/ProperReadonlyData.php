<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    // Righteous: All public properties are readonly
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone = null,
    ) {}
}
