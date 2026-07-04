<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\TransformerWithoutTsType;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;

/**
 * RIGHTEOUS: transformers that the detector must NOT flag — a custom transformer PAIRED with the
 * transformed TS type (both `#[TypeScriptType]` and `#[LiteralTypeScriptType]` forms), and a built-in
 * transformer whose target type the generator already knows.
 */
#[Righteous(TransformerWithoutTsType::class)]
final class WirePairedData extends Data
{
    public function __construct(
        #[WithTransformer(MoneyTransformer::class), TypeScriptType('string')]
        public readonly Money $price,

        #[LiteralTypeScriptType('[number, number]'), WithTransformer(GeoPointTransformer::class)]
        public readonly GeoPoint $location,

        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        public readonly \Carbon\Carbon $createdAt,
    ) {}
}
