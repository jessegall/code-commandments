<?php

declare(strict_types=1);

namespace Billing\Gateways;

interface PaymentGateway
{
    public function charge(int $amountCents): void;
}
