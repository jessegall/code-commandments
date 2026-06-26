<?php

namespace Shop\Concurrent;

use JesseGall\CodeCommandments\Detectors\Backend\ConcurrentSubclassDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use JesseGall\Concurrent\Concurrent;

/**
 * Shared cart state — but subclassing the proxy instead of composing it, so the
 * domain object is welded to the Concurrent API. The righteous twin is
 * CheckoutSession (plain object + ::for()).
 */
#[Sinful(ConcurrentSubclassDetector::class)]
final class CartSession extends Concurrent
{
    /** @var array<int, int> */
    public array $quantities = [];

    public ?string $couponCode = null;

    public function add(int $productId, int $quantity): void
    {
        $this->quantities[$productId] = ($this->quantities[$productId] ?? 0) + $quantity;
    }

    public function drop(int $productId): void
    {
        unset($this->quantities[$productId]);
    }

    public function applyCoupon(string $code): void
    {
        $this->couponCode = $code;
    }

    public function totalItems(): int
    {
        return array_sum($this->quantities);
    }
}
