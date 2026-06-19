<?php

declare(strict_types=1);

namespace Account;

final class UserRepository
{
    /** @param array<int, User> $users */
    public function __construct(
        private array $users = [],
    ) {}

    /**
     * Honest contract: resolve the user or throw. (This part is fine — the
     * smell is the CALLER swallowing the throw; see UserProfileService.)
     */
    public function getById(int $id): User
    {
        return $this->users[$id] ?? throw UserNotFoundException::forId($id);
    }
}
