<?php

namespace Shop\Billing;

/**
 * Routes a checkout's tender to the right gateway.
 */
final class PaymentRouter
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function route(?GiftCard $card): int
    {
        return $this->gateway->authorize($card);
    }
}
