<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualOutputTransform;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;

/**
 * A getter hook hand-flattens a `Money` value object into its wire array — the honest `Money` type is
 * lost and the shape is re-authored per page. A `#[WithTransformer(MoneyTransformer::class)]` on a real
 * `Money` slot (plus a `#[TypeScriptType]`) should own the serialized shape.
 */
final class PricingPage extends Data
{
    #[Sinful(ManualOutputTransform::class)]
    #[Computed]
    public array $priceInEuro { get => ['amount' => $this->money->cents, 'currency' => $this->money->code]; }

    #[Computed]
    public string $tier {
        get => match (true) {
            $this->quantity >= 100 => 'wholesale',
            $this->quantity >= 12 => 'bulk',
            default => 'retail',
        };
    }

    public function __construct(
        public readonly Money $money,
        public readonly string $sku,
        public readonly int $quantity,
    ) {}

}

/**
 * The FIX for the same pricing page: the slot keeps its honest `Money` type and a
 * `#[WithTransformer(MoneyTransformer::class)]` owns the serialized shape (with a `#[TypeScriptType]`
 * declaring it), so no getter hand-builds the wire array.
 */
final class TransformedPricingPage extends Data
{
    #[Fixed(ManualOutputTransform::class)]
    public function __construct(
        #[WithTransformer(MoneyTransformer::class), TypeScriptType('string')]
        public readonly Money $priceInEuro,
        public readonly string $sku,
        public readonly int $quantity,
    ) {}
}
