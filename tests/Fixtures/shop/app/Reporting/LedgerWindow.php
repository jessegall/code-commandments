<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Detectors\Backend\MaskedInvariantDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A report focuses on a period, then asks `$this->period?->includes(...) ?? false`
 * for every row. Once focused the period never unsets — the nullsafe and the
 * default merely re-prove what `focus()` already guaranteed.
 */
final class LedgerWindow
{
    private ?Period $period = null;

    public function __construct(private readonly string $currency = 'EUR') {}

    public function focus(string $from, string $to): void
    {
        $this->period = new Period($from, $to);
    }

    #[Sinful(MaskedInvariantDetector::class)]
    public function covers(string $date): bool
    {
        return $this->period?->includes($date) ?? false;
    }

    public function label(): string
    {
        return sprintf('%s ledger', $this->currency);
    }
}

final class Period
{
    public function __construct(private readonly string $from, private readonly string $to) {}

    public function includes(string $date): bool
    {
        return $date >= $this->from && $date <= $this->to;
    }
}
