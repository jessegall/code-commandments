<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * A presentation classification built by a from-source factory — the righteous twin of a type switch.
 * The match asks what a ledger line IS, which looks like the sin and is not one: moving it onto the
 * line would make the domain name its own presentation, and this arrow is the reason the mapping sits
 * on the enum that renders it (#509). Told apart structurally — static, returning the enclosing type.
 */
#[Righteous]
enum LedgerTone
{
    case Credit;

    case Debit;

    case Adjustment;

    public static function of(object $line): self
    {
        return match (true) {
            $line instanceof CreditLine => self::Credit,
            $line instanceof DebitLine => self::Debit,
            $line instanceof AdjustmentLine => self::Adjustment,
            default => throw new UnhandledLedgerLine($line::class),
        };
    }
}
