<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Records the financial outcome of payment events against an order.
 */
interface OrderLedger
{
    public function reconcile(string $orderId, WebhookEvent $event): void;
}
