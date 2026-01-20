<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}

    // Sin: Custom fromModel method instead of using Spatie's from()
    public static function fromModel(User $user): self
    {
        return new self(
            name: $user->name,
            email: $user->email,
        );
    }

    // Sin: Custom fromEntity method
    public static function fromEntity(UserEntity $entity): self
    {
        return new self(
            name: $entity->getName(),
            email: $entity->getEmail(),
        );
    }
}
