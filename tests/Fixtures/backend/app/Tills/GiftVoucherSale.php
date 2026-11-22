<?php

namespace Shop\Tills;

/**
 * A gift voucher sold at the till, the one sale the till can take back.
 */
final readonly class GiftVoucherSale implements Refundable
{
    public function __construct(private int $cents) {}

    public function refundCents(): int
    {
        return $this->cents;
    }
}
