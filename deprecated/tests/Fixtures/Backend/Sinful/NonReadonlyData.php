<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    // Sin: Non-readonly public properties in Data class
    public string $name;
    public string $email;
    public ?string $phone;

    public function __construct(
        // Sin: Non-readonly promoted properties
        public string $firstName,
        public string $lastName,
    ) {
        $this->name = $firstName . ' ' . $lastName;
    }
}
