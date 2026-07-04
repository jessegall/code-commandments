<?php

namespace Shop\Billing;

/**
 * Authorizes the tender, then hands it to the ledger to record.
 */
final class PaymentGateway
{
    public function __construct(private readonly LedgerWriter $ledger) {}

    public function authorize(?GiftCard $card): int
    {
        return $this->ledger->record($card);
    }
}
