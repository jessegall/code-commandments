<?php

namespace Shop\Http\Pages\Hydration;

use Shop\Enums\PaymentMethod;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantEnumUnwrap;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * Scenario 2 — the enum arrives as a parameter directly (not off an object), guarded, then destructured to
 * `->value` at its own enum slot. A different shape from the object-property read of scenario 1.
 */
final class PaymentIntent extends Data
{
    public function __construct(public readonly PaymentMethod $method, public readonly string $reference) {}
}

final class PaymentIntentBuilder
{
    #[Sinful(RedundantEnumUnwrap::class)]
    public function open(PaymentMethod $method, string $reference): PaymentIntent
    {
        if ($reference === '') {
            $reference = 'pending';
        }

        return PaymentIntent::from(['method' => $method->value, 'reference' => $reference]);
    }
}
