<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Messy;

/** A rendered customer statement. */
final readonly class Statement
{
    public function __construct(
        public Recipient $recipient,
        public string $body,
    ) {}
}
