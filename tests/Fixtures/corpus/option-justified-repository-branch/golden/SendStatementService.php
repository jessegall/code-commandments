<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Golden;

/** Call site #2: absence is a hard domain error AND the value is threaded through transforms. */
final readonly class SendStatementService
{
    public function __construct(
        private CustomerRepository $customers,
    ) {}

    public function buildStatement(string $email): Statement
    {
        // The found customer is threaded through a chain of transforms. Option
        // lets us map without an intermediate null check, and getOrThrow makes
        // "no such customer" an explicit, un-skippable domain failure.
        return $this->customers->findByEmail($email)
            ->map(fn (Customer $c) => new Recipient($c->name, $c->email))
            ->map(fn (Recipient $r) => new Statement($r, "Statement for {$r->name}"))
            ->getOrThrow(fn () => new \DomainException("No customer for {$email}"));
    }
}
