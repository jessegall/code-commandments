<?php

namespace Shop\Payments;

use JesseGall\CodeCommandments\Sins\Backend\DataClump;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * An implementation repeats the signature its interface declares — the contract's parameters, not a
 * clump this class chose.
 */
final class MollieCallback implements PaymentCallback
{
    public array $settled = [];

    #[Righteous(DataClump::class)]
    public function handleReturn(string $orderNumber, string $providerReference, string $status): void
    {
        $this->settled[$orderNumber] = [$providerReference, $status];
    }
}
