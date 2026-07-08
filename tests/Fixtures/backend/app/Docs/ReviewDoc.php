<?php

namespace Shop\Docs;

use JesseGall\CodeCommandments\Sins\Backend\DanglingDocReference;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Moderates product reviews. The scoring model lives in {@see \Shop\Trust\GhostScorer}, which no longer
 * exists — the documentation was never repointed.
 */
#[Sinful(DanglingDocReference::class)]
final class ReviewDoc
{
    public function approve(int $stars, bool $verified): bool
    {
        return $verified && $stars >= 3;
    }

    public function flagged(string $body): bool
    {
        return str_contains($body, 'spam');
    }

    public function digest(int $count): string
    {
        return $count === 1 ? '1 review' : "{$count} reviews";
    }
}
