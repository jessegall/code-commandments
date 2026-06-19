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
        // Invariant: just use it. A bad session id surfaces as a real error.
        $myName = $this->directory->getById($sessionUserId)->displayName();

        // Genuine absence: model it with Option — the search miss is expected.
        $foundName = $this->directory->findByEmail($searchEmail)
            ->map(fn (User $u): string => $u->displayName())
            ->getOr('not found');

        return "{$myName} searched for {$foundName}";
    }
}
