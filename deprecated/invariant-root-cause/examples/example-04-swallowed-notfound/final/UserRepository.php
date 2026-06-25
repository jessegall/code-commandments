<?php

declare(strict_types=1);

namespace Account;

final class UserRepository
{
    /** @param array<int, User> $users */
    public function __construct(
        private array $users = [],
    ) {}

    public function getById(int $id): User
    {
        return $this->users[$id] ?? throw UserNotFoundException::forId($id);
    }
}
