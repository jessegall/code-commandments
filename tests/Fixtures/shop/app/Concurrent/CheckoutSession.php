<?php

namespace Shop\Concurrent;

use JesseGall\Concurrent\Concurrent;

/**
 * Transient per-customer checkout state, shared across requests.
 */
final class CheckoutSession
{
    private const int TTL = 1800;

    private int $itemCount = 0;

    /**
     * @return Concurrent<self>
     */
    public static function for(int $customerId): Concurrent
    {
        return new Concurrent(
            key: "checkout:{$customerId}",
            default: new self,
            ttl: self::TTL,
        );
    }

    public function addItem(): void
    {
        $this->itemCount++;
    }
}
