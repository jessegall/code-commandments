<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Golden;

use RuntimeException;

/** Caller #2: absence is a domain error — the invitee must already exist. */
final readonly class InviteCollaboratorAction
{
    public function __construct(
        private UserDirectory $directory,
    ) {}

    public function invite(string $email): User
    {
        return $this->directory
            ->findByEmail($email)
            ->getOrThrow(fn () => new RuntimeException("No user found for {$email}"));
    }
}
