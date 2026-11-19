<?php

namespace Shop\Vouchers;

use JesseGall\CodeCommandments\Sins\Backend\LoneImplementerInterface;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * Replaces a lost voucher with a new code, depending on the one generator the shop has.
 */
#[Fixed(LoneImplementerInterface::class)]
final class VoucherReissuer
{
    public function __construct(private readonly RandomCodeGenerator $codes) {}

    public function reissue(string $lost): string
    {
        return $lost.'-R-'.$this->codes->next();
    }
}
