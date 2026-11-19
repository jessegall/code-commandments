<?php

namespace Shop\Loyalty\Unused;

use JesseGall\CodeCommandments\Sins\Backend\UnusedPrivateMember;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Loyalty points per customer, with a conversion table nobody consults any more.
 */
#[Sinful(UnusedPrivateMember::class)]
final class PointsLedger
{
    /**
     * @var array<string, float>
     */
    private static array $rates = ['silver' => 1.0, 'gold' => 1.5];

    /**
     * @var array<int, int>
     */
    private array $points = [];

    public function award(int $customerId, int $cents): void
    {
        $this->points[$customerId] = ($this->points[$customerId] ?? 0) + intdiv($cents, 100);
    }

    public function pointsOf(int $customerId): int
    {
        return $this->points[$customerId] ?? 0;
    }
}
