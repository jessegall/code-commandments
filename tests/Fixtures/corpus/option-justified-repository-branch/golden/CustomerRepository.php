<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Golden;

use JesseGall\PhpTypes\Option;

/** Loads customers; absence is a meaningful domain branch, so it returns Option. */
final readonly class CustomerRepository
{
    /** @param array<string, Customer> $byEmail */
    public function __construct(
        private array $byEmail = [],
    ) {}

    /** @return Option<Customer> */
    public function findByEmail(string $email): Option
    {
        return Option::fromNullable($this->byEmail[$email] ?? null);
    }
}
