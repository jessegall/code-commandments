<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Detectors\Backend\DuplicateFunctionDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class LoyaltyDigest
{
    private int $points = 0;

    private int $lifetime = 0;

    public function earn(int $amount): void
    {
        $gained = max(0, $amount);
        $this->points += $gained;
        $this->lifetime += $gained;
    }

    public function redeem(int $amount): bool
    {
        if ($amount > $this->points) {
            return false;
        }

        $this->points -= $amount;

        return true;
    }

    public function tier(): string
    {
        return match (true) {
            $this->lifetime >= 5000 => 'platinum',
            $this->lifetime >= 1000 => 'gold',
            default => 'silver',
        };
    }

    public function balance(): int
    {
        return $this->points;
    }

    #[Sinful(DuplicateFunctionDetector::class)]
    public function fingerprint(int $base, int $count): string
    {
        $total = $base;

        for ($i = 0; $i < $count; $i++) {
            $total += $i * 2;
        }

        return md5((string) $total);
    }
}
