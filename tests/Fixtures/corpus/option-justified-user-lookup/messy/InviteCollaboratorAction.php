<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Messy;

use RuntimeException;

/** Caller #2: absence is a domain error — but nothing FORCES this guard; forget it and a null leaks downstream. */
final readonly class InviteCollaboratorAction
{
    public function __construct(
        private UserDirectory $directory,
    ) {}

    public function invite(string $email): User
    {
        $user = $this->directory->findByEmail($email);

        if ($user === null) {
            throw new RuntimeException("No user found for {$email}");
        }

        return $user;
    }
}
