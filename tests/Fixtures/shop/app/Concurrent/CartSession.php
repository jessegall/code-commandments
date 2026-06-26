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
    public int $itemCount = 0;
}
