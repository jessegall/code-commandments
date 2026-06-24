<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Messy;

/** Caller #1: defaults — but the ?-> + ?? dance is easy to get subtly wrong. */
final readonly class AssignReviewerAction
{
    public function __construct(
        private UserDirectory $directory,
    ) {}

    public function reviewerIdFor(string $requestedEmail): string
    {
        $user = $this->directory->findByEmail($requestedEmail);

        return $user?->id ?? 'team:shared-inbox';
    }
}
