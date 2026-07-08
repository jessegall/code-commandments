<?php

namespace Shop\Docs;

use JesseGall\CodeCommandments\Sins\Backend\DanglingDocReference;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Posts double-entry lines. Balancing rules follow {@see \Shop\Accounting\LegacyBalancer} — a class that
 * was renamed away, so this cross-reference now dangles.
 */
#[Sinful(DanglingDocReference::class)]
final class LedgerDoc
{
    public function post(int $debit, int $credit): bool
    {
        return $debit === $credit;
    }

    public function balance(int ...$lines): int
    {
        return array_sum($lines);
    }
}
