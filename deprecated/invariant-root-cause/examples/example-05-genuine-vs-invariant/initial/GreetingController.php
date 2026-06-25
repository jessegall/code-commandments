<?php

declare(strict_types=1);

namespace Directory;

final class GreetingController
{
    public function __construct(
        private UserDirectory $directory,
    ) {}

    public function greet(int $sessionUserId, string $searchEmail): string
    {
        // INVARIANT laundered through the wrapper: the session user must exist,
        // but getById() returns null, so MaybeUser papers it over as "Guest".
        $me = new MaybeUser($this->directory->getById($sessionUserId));

        // GENUINE absence: a search miss is fine.
        $found = $this->directory->findByEmail($searchEmail);

        $myName = $me->orGuest()->displayName();
        $foundName = $found?->displayName() ?? 'not found';

        return "{$myName} searched for {$foundName}";
    }

    public function invite(string $email): string
    {
        // A second site that BRANCHES on the same absence — a search miss is a
        // different outcome here (re-invite), so the callers juggle it divergently.
        $user = $this->directory->findByEmail($email);

        if ($user === null) {
            return "invite sent to {$email}";
        }

        return "{$user->displayName()} already joined";
    }

    public function exists(string $email): bool
    {
        return $this->directory->findByEmail($email) !== null;
    }
}
