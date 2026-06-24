<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Messy;

/** A placed order. */
final readonly class Order
{
    public function __construct(
        public string $customerId,
        public int $amountCents,
        public string $currency,
    ) {}
}
