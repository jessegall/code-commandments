<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Golden;

/** A customer aggregate keyed by email. */
final readonly class Customer
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public string $defaultCurrency,
    ) {}
}
