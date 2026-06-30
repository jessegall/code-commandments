<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Detectors\Backend\EnumValueMatchDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\Mailer;
use Shop\Models\Order;

/**
 * Sends order notifications — re-deciding the message from the status enum's
 * scalar inside a call argument, behaviour that belongs on OrderStatus.
 */
final class OrderNotifier
{
    public function __construct(private readonly Mailer $mailer) {}

    #[Sinful(EnumValueMatchDetector::class)]
    public function notify(Order $order): void
    {
        $this->mailer->send(
            $order->customer_email,
            'Order update',
            match ($order->status->value) {
                'pending' => 'We received your order and will process it shortly.',
                'paid' => 'Your payment has been confirmed.',
                'shipped' => 'Good news — your order is on its way.',
                'cancelled' => 'Your order has been cancelled.',
                default => 'There is an update on your order.',
            },
        );
    }
}
