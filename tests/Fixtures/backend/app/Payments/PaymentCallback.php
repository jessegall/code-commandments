<?php

namespace Shop\Payments;

/**
 * The redirect a payment provider sends the customer back through, as each provider's integration
 * handles it.
 */
interface PaymentCallback
{
    public function handleReturn(string $orderNumber, string $providerReference, string $status): void;
}
