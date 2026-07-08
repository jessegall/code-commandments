<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedTypeGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * `$line instanceof SaleLine && $line->product instanceof TaxedGood` repeated across a predicate and a
 * match-based classifier — a distinct guard, a distinct shape.
 */
final class InvoiceRules
{
    #[Sinful(RepeatedTypeGuard::class)]
    public function taxable($line): bool
    {
        return $line instanceof SaleLine && $line->product instanceof TaxedGood;
    }

    #[Sinful(RepeatedTypeGuard::class)]
    public function band($line): string
    {
        return match ($line instanceof SaleLine && $line->product instanceof TaxedGood) {
            true => 'vat',
            default => 'net',
        };
    }

    public function gross(float $net, float $ratePercent): float
    {
        return round($net * (1.0 + $ratePercent / 100.0), 2);
    }

    public function heading(string $customer, string $number): string
    {
        return strtoupper($number) . ' — ' . ucfirst($customer);
    }
}
