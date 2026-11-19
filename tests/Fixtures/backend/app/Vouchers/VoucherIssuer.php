<?php

namespace Shop\Vouchers;

use Shop\Vouchers\Contracts\CodeGenerator;

/**
 * Hands out a fresh voucher code for each gift the shop gives.
 */
final class VoucherIssuer
{
    public function __construct(private readonly CodeGenerator $codes) {}

    public function issue(): string
    {
        return 'V-'.$this->codes->next();
    }
}
