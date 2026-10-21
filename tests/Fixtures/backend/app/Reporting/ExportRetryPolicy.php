<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\UnreadCollaborator;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\HttpClient;

/**
 * Says when a failed export is tried again: a doubling back-off, capped, with the last
 * attempt given up on. The client the constructor asks for takes no part in any of it.
 */
final class ExportRetryPolicy
{
    private const CEILING = 60;

    private const ATTEMPTS = 5;

    #[Sinful(UnreadCollaborator::class)]
    public function __construct(private readonly HttpClient $http) {}

    public function waitFor(int $attempt): int
    {
        return min(2 ** $attempt, self::CEILING);
    }

    public function isSpent(int $attempt): bool
    {
        return $attempt >= self::ATTEMPTS;
    }

    public function totalWait(): int
    {
        $total = 0;
        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $total += $this->waitFor($attempt);
        }

        return $total;
    }
}
