<?php

namespace Shop\Billing;

use JesseGall\CodeCommandments\Sins\Backend\SideTableTrait;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Which entries a correction may reverse is a constant listing them, asked by a static method
 * rather than by the entry itself. The kind cannot answer for its own reversibility.
 */
#[Sinful(SideTableTrait::class)]
enum LedgerEntry: string
{
    case Charge = 'charge';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Writeoff = 'writeoff';

    private const REVERSIBLE = [self::Charge, self::Adjustment];

    public static function reversible(self $entry): bool
    {
        return in_array($entry, self::REVERSIBLE, true);
    }

    public static function postedBy(string $narrative): self
    {
        return self::tryFrom(strtolower(trim($narrative))) ?? self::Adjustment;
    }

    public function signedAmount(int $pence): int
    {
        return $this === self::Refund || $this === self::Writeoff ? -$pence : $pence;
    }
}
