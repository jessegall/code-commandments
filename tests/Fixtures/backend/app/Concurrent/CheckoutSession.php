<?php

namespace Shop\Concurrent;

use JesseGall\CodeCommandments\Sins\Backend\MemberAfterMethod;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\Concurrent\Concurrent;

/**
 * Transient per-customer checkout state, shared across requests. Righteous twin: the TTL and the item
 * count stand at the head of the class, so one read of the top says everything this object holds.
 */
#[Righteous(MemberAfterMethod::class)]
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
