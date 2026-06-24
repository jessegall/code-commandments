<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Messy;

/** A statement recipient. */
final readonly class Recipient
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
