<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\TransformerWithoutTsType;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

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
