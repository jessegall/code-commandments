<?php

namespace Shop\Http\Pages\Hydration;

use Shop\Enums\OrderStatus;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantEnumUnwrap;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Data;

/*
 * Righteous twin for RedundantEnumUnwrap — the enum is passed straight into its slot (nothing to unwrap),
 * and the one `->value` here feeds a PLAIN array, not a `Data::from`, where the scalar is genuinely wanted.
 */
final class TrackedOrder extends Data
{
    public function __construct(public readonly OrderStatus $status) {}
}

final class TrackedOrderFactory
{
    #[Righteous(RedundantEnumUnwrap::class)]
    public function track(OrderStatus $status): TrackedOrder
    {
        return TrackedOrder::from(['status' => $status]);
    }

    /**
     * @return array<string, string>
     */
    public function payload(OrderStatus $status): array
    {
        return ['status' => $status->value];
    }
}
