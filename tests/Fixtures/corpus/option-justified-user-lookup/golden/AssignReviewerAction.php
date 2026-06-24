<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Golden;

/** Caller #1: absence is fine — falls back to a shared inbox. */
final readonly class AssignReviewerAction
{
    public function __construct(
        private UserDirectory $directory,
    ) {}

    public function reviewerIdFor(string $requestedEmail): string
    {
        return $this->directory
            ->findByEmail($requestedEmail)
            ->map(fn (User $u) => $u->id)
            ->getOrElse('team:shared-inbox');
    }
}
