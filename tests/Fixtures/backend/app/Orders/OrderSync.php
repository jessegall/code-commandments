<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\SwallowCatch;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Pushes an order to the ERP and swallows any failure into a null reference, so a
 * failed sync looks exactly like a successful one that returned nothing.
 */
final class OrderSync
{
    #[Sinful(SwallowCatch::class)]
    public function push(int $orderId): ?string
    {
        try {
            return $this->send($orderId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function send(int $orderId): string
    {
        return "erp-{$orderId}";
    }
}
