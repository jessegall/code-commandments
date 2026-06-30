<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\OptionAsNullable;

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
    #[Sinful(OptionAsNullable::class)]
    public function emailFor(Option $order): ?string
    {
        return $order->unwrapOr(null)?->customer_email;
    }

    public function emailOrGuest(Option $order): string
    {
        return $order->unwrapOr('guest@shop.test');
    }

    /**
     * Adapting the Option to a nullable-sink parameter at the boundary — `withTags(?array
     * $tags)` discriminates on null, so `unwrapOr(null)` in ARGUMENT position is the
     * legitimate adaptation, NOT a `?Option` costume (no marker).
     *
     * @param  Option<array<int, string>>  $tags
     */
    public function dispatch(Option $tags): void
    {
        $this->withTags(tags: $tags->unwrapOr(null));
    }

    private function withTags(?array $tags): void
    {
        // null = unconstrained; [] = explicitly none — a deliberate tri-state.
    }
}
