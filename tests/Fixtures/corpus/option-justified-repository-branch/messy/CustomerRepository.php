<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Messy;

/** Loads customers; returns a bare nullable, so absence is invisible at the type level. */
final readonly class CustomerRepository
{
    /** @param array<string, Customer> $byEmail */
    public function __construct(
        private array $byEmail = [],
    ) {}

    public function findByEmail(string $email): ?Customer
    {
        return $this->byEmail[$email] ?? null;
    }
}
