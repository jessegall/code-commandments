<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\TransformerWithoutTsType;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;

/**
 * A custom `MoneyTransformer` reshapes `Money` to a string on the wire, but no `#[TypeScriptType]` says
 * so — the generated TS keeps `Money` while the wire carries a string. The transformer needs a paired
 * `#[TypeScriptType('string')]`.
 */
final class WireMoneyData extends Data
{
    #[Sinful(TransformerWithoutTsType::class)]
    public function __construct(
        #[WithTransformer(MoneyTransformer::class)]
        public readonly Money $price,
    ) {}

    public function isPaid(): bool
    {
        return $this->price->cents > 0;
    }
}

/**
 * The FIX for the same money slot: the custom transformer is PAIRED with a `#[TypeScriptType('string')]`
 * declaring the shape it serializes to, so the generated frontend type matches what the wire carries.
 */
final class WirePricedData extends Data
{
    #[Fixed(TransformerWithoutTsType::class)]
    public function __construct(
        #[WithTransformer(MoneyTransformer::class), TypeScriptType('string')]
        public readonly Money $price,
    ) {}

    public function isFree(): bool
    {
        return $this->price->cents === 0;
    }
}
