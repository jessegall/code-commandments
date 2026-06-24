<?php

namespace App\Webhooks;

class WebhookEvent
{
    const ORDER_CREATED = 'order.created';
    const PAYMENT_SUCCEEDED = 'payment.succeeded';
    const PAYMENT_REFUNDED = 'payment.refunded';
}
