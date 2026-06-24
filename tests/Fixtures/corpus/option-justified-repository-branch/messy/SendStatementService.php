<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Messy;

/** Call site #2: the null re-coalesces through every transform with guards stacking up. */
final readonly class SendStatementService
{
    public function __construct(
        private CustomerRepository $customers,
    ) {}

    public function buildStatement(string $email): Statement
    {
        $customer = $this->customers->findByEmail($email);

        // Every step has to re-prove the value is present. The guard is repeated,
        // the transforms can't chain, and it's easy to let one `?->` silently
        // produce a half-built null Recipient instead of failing loudly.
        if ($customer === null) {
            throw new \DomainException("No customer for {$email}");
        }

        $recipient = new Recipient($customer->name, $customer->email);

        return new Statement($recipient, "Statement for {$recipient->name}");
    }
}
