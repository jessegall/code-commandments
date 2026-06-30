<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;

use JesseGall\CodeCommandments\Testing\Sinful;

final class SalesDigest
{
    private int $orders = 0;

    private int $revenueCents = 0;

    private int $peakCents = 0;

    public function record(int $cents): void
    {
        $this->orders++;
        $this->revenueCents += $cents;

        if ($cents > $this->peakCents) {
            $this->peakCents = $cents;
        }
    }

    public function averageOrderValue(): int
    {
        if ($this->orders === 0) {
            return 0;
        }

        return intdiv($this->revenueCents, $this->orders);
    }

    public function peak(): int
    {
        return $this->peakCents;
    }

    public function formatRevenue(string $currency): string
    {
        $major = intdiv($this->revenueCents, 100);
        $minor = $this->revenueCents % 100;

        return sprintf('%s %d.%02d', $currency, $major, $minor);
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
}
