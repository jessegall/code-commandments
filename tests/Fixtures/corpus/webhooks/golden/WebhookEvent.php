<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * The set of inbound webhook events this application accepts.
 */
enum WebhookEvent: string
{
    /** A new order was placed and needs fulfilment. */
    case OrderCreated = 'order.created';

    /** A payment cleared for an existing order. */
    case PaymentSucceeded = 'payment.succeeded';

    /** A payment was refunded and the order must be reversed. */
    case PaymentRefunded = 'payment.refunded';

    public function isPayment(): bool
    {
        return match ($this) {
            WebhookEvent::PaymentSucceeded, WebhookEvent::PaymentRefunded => true,
            WebhookEvent::OrderCreated => false,
        };
    }
}
