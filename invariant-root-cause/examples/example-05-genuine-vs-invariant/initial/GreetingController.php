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
}
