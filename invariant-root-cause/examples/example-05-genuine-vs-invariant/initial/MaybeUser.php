<?php

declare(strict_types=1);

namespace Directory;

/**
 * SMELL (whole class): a hand-rolled half-Option that exists ONLY because
 * UserDirectory::getById() returns null. It re-implements a sliver of the
 * Option monad (`isPresent` / `orGuest`) and spreads the nullable contract
 * further. Once getById() throws (the invariant) this class has no reason to
 * exist.
 */
final class MaybeUser
{
    public function __construct(
        private ?User $user,
    ) {}

    public function isPresent(): bool
    {
        return $this->user !== null;
    }

    public function orGuest(): User
    {
        return $this->user ?? User::guest();
    }
}
