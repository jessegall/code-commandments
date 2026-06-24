<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Golden;

use JesseGall\PhpTypes\Option;

/** Finds users by email, returning Option to force callers to decide on absence. */
final readonly class UserDirectory
{
    /** @param array<string, User> $byEmail */
    public function __construct(
        private array $byEmail,
    ) {}

    /** @return Option<User> */
    public function findByEmail(string $email): Option
    {
        return Option::fromNullable($this->byEmail[strtolower($email)] ?? null);
    }
}
