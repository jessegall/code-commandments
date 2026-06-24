<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Messy;

/** Finds users by email, returning a bare nullable that every caller must re-handle. */
final readonly class UserDirectory
{
    /** @param array<string, User> $byEmail */
    public function __construct(
        private array $byEmail,
    ) {}

    public function findByEmail(string $email): ?User
    {
        return $this->byEmail[strtolower($email)] ?? null;
    }
}
