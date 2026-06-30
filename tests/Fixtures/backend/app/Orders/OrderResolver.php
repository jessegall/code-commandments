<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Detectors\Backend\OptionAsNullableDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use JesseGall\PhpTypes\Option;

/**
 * Collapses an Option straight back to a null with `unwrapOr(null)` — undoing the
 * very thing the Option was for. The honest twin unwraps to a real default.
 */
final class OrderResolver
{
    /**
     * @param  Option<\Shop\Models\Order>  $order
     */
    #[Sinful(OptionAsNullableDetector::class)]
    public function emailFor(Option $order): ?string
    {
        return $order->unwrapOr(null)?->customer_email;
    }

    public function emailOrGuest(Option $order): string
    {
        return $order->unwrapOr('guest@shop.test');
    }
}
