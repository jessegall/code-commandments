<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\MaskedInvariant;

use JesseGall\CodeCommandments\Testing\Righteous;
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

    #[Sinful(MaskedInvariant::class)]
    public function covers(string $date): bool
    {
        return $this->period?->includes($date) ?? false;
    }

    /**
     * Resolve-or-throw: the invariant ("focus() ran first") is asserted, not papered
     * over with a fake default for a state that can only be a bug.
     */
    #[Righteous(MaskedInvariant::class)]
    public function coversOrFail(string $date): bool
    {
        if ($this->period === null) {
            throw LedgerNotFocused::beforeCovers();
        }

        return $this->period->includes($date);
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
