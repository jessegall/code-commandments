<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Golden;

/** A statement recipient. */
final readonly class Recipient
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
