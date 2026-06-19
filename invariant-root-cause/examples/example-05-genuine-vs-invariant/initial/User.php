<?php

declare(strict_types=1);

namespace Directory;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $name,
    ) {}

    public function displayName(): string
    {
        return $this->name;
    }

    public static function guest(): self
    {
        return new self(0, 'guest@example.test', 'Guest');
    }
}
