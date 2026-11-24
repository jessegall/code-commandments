<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullableWireObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * Scenario 4 — a delivery leg whose optional side is a PURE enum typed `| null`. A pure enum has
 * no scalar of its own, so the absence belongs in `LegSide | Optional`, which omits the key.
 */
#[Sinful(NullableWireObject::class)]
#[TypeScript]
final class WireLeg extends Data
{
    public function __construct(
        public readonly int $minutes,
        public readonly ?LegSide $side = null,
    ) {}
}

#[TypeScript]
enum LegSide
{
    case Kerb;
    case Rear;
}
