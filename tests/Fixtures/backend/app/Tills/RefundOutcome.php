<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\CoupledFields;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * How a refund at the till ended: paid back with a receipt number, or declined with a reason, never both.
 */
#[Righteous(CoupledFields::class)]
final class RefundOutcome
{
    private ?string $receipt = null;

    private ?string $declined = null;

    private int $attempts = 0;

    public function pay(string $receipt): void
    {
        $this->receipt = $receipt;
        $this->attempts++;
    }

    public function decline(string $reason): void
    {
        $this->declined = $reason;
        $this->attempts++;
    }

    public function isPaid(): bool
    {
        return $this->receipt !== null && $this->declined === null;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }
}
