<?php

namespace Shop\Payments;

use JesseGall\CodeCommandments\Sins\Backend\DataClump;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The same contract, implemented for a second provider.
 */
final class StripeCallback implements PaymentCallback
{
    public array $received = [];

    #[Righteous(DataClump::class)]
    public function handleReturn(string $orderNumber, string $providerReference, string $status): void
    {
        $this->received[] = "{$orderNumber}:{$providerReference}:{$status}";
    }
}
