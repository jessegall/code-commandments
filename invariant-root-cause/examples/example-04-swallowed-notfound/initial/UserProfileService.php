<?php

declare(strict_types=1);

namespace Account;

final class UserProfileService
{
    public function __construct(
        private UserRepository $users,
    ) {}

    /**
     * SMELL: the id is the AUTHENTICATED user — it must exist. Swallowing the
     * not-found into null turns a corrupt-session bug into a silent "Guest",
     * and the caller-side `?-> … ?? 'Guest'` cements it.
     */
    public function displayName(int $authenticatedUserId): string
    {
        try {
            $user = $this->users->getById($authenticatedUserId);
        } catch (UserNotFoundException) {
            $user = null;
        }

        return $user?->displayName() ?? 'Guest';
    }
}
