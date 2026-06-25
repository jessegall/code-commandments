<?php

namespace Shop\Services;

use Shop\Contracts\Mailer;
use Shop\Models\Customer;

/**
 * Sends transactional email through the injected mailer.
 */
final class EmailService
{
    public function __construct(private readonly Mailer $mailer) {}

    public function sendReceipt(Customer $customer, int $orderId): void
    {
        $this->mailer->send($customer->email, "Receipt for order {$orderId}", 'Thanks for your order.');
    }
}
