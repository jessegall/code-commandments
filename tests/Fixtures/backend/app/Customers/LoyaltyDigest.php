<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
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

    #[Sinful(DuplicateFunction::class)]
    public function fingerprint(int $base, int $count): string
    {
        $total = $base;

        for ($i = 0; $i < $count; $i++) {
            $total += $i * 2;
        }

        return md5((string) $total);
    }

    #[Righteous(DuplicateFunction::class)]
    public function checksum(int $base, int $count): string
    {
        return $this->fingerprint($base, $count);
    }
}

/**
 * The FIX: the copies are GONE. The loop the loyalty, sales and stock digests each kept their own
 * copy of now lives here once — each digest calls `DigestFingerprint::of(...)` and none of them owns
 * a `fingerprint()` any more, so the next change is made in one place or nowhere.
 */
final class DigestFingerprint
{
    #[Fixed(DuplicateFunction::class)]
    public static function of(int $base, int $count): string
    {
        $steps = array_map(static fn (int $i): int => $i * 2, range(0, max(0, $count - 1)));

        return md5((string) ($base + array_sum($steps)));
    }
}
