<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\TransformerWithoutTsType;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Computed;

/**
 * A custom `GeoPointTransformer` flattens `GeoPoint` to a `[lat, lng]` tuple on the wire with no paired
 * `#[TypeScriptType]` / `#[LiteralTypeScriptType]` — so the frontend type is wrong. Declared on a
 * property here rather than a promoted param, to vary the shape.
 */
final class WireGeoData extends Data
{
    #[Sinful(TransformerWithoutTsType::class)]
    #[WithTransformer(GeoPointTransformer::class)]
    public GeoPoint $location;

    public function __construct(GeoPoint $location)
    {
        $this->location = $location;
    }

    #[Computed]
    public float $latitude {
        get => $this->location->lat;
    }
}
