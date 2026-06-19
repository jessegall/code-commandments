<?php

declare(strict_types=1);

namespace Account;

final class UserProfileService
{
    public function __construct(
        private UserRepository $users,
    ) {}

    /**
     * The id is the authenticated user — an invariant. If it is gone, that is a
     * real error and must surface, not degrade to "Guest". No try/catch, no
     * fallback: just use the value.
     */
    public function displayName(int $authenticatedUserId): string
    {
        return $this->users->getById($authenticatedUserId)->displayName();
    }
}
