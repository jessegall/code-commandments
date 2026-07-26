<?php

namespace Shop\Http\Pages\Hydration;

use Shop\Enums\OrderStatus;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantEnumUnwrap;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * Scenario 1 — a status enum read off a basket object and destructured to `->value` at an enum slot that
 * auto-casts. The other field is a derived count, so the class does real work around the unwrap.
 */
final class CheckoutSummary extends Data
{
    public function __construct(public readonly OrderStatus $status, public readonly int $lines) {}
}

final class Basket
{
    /**
     * @param list<string> $items
     */
    public function __construct(public OrderStatus $status, public array $items) {}
}

final class CheckoutSummaryFactory
{
    #[Sinful(RedundantEnumUnwrap::class)]
    public function summarise(Basket $basket): CheckoutSummary
    {
        return CheckoutSummary::from(['status' => $basket->status->value, 'lines' => count($basket->items)]);
    }
}
