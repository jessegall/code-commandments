<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Golden;

/** A rendered customer statement. */
final readonly class Statement
{
    public function __construct(
        public Recipient $recipient,
        public string $body,
    ) {}
}
