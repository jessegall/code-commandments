<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\PhpTypes\OptionAsNullable;

use JesseGall\CodeCommandments\Testing\Righteous;
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
     * Hands the Option to the project's own `withTags(?array $tags)` as a null: the method is ours, so it
     * can take the Option itself.
     *
     * @param  Option<array<int, string>>  $tags
     */
    #[Sinful(OptionAsNullable::class)]
    public function dispatch(Option $tags): void
    {
        $this->withTags(tags: $tags->unwrapOr(null));
    }

    /**
     * Writes the tags to the wire, where JSON's null is the format: a library signature the project cannot change.
     *
     * @param  Option<array<int, string>>  $tags
     */
    #[Righteous(OptionAsNullable::class)]
    public function tagsPayload(Option $tags): string
    {
        return (string) json_encode($tags->unwrapOr(null));
    }

    private function withTags(?array $tags): void
    {
        // null = unconstrained; [] = explicitly none — a deliberate tri-state.
    }
}
