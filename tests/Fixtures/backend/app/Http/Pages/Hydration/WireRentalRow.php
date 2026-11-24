<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullableWireObject;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Rentals\RentalStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * Righteous twin for NullableWireObject — the nullable fields are BACKED enums. A backed enum
 * travels as its scalar, so `?RentalStatus` ships a string or null, which the frontend reads as
 * a plain nullable value (#860, #954). Must NOT flag.
 */
#[Righteous(NullableWireObject::class)]
#[TypeScript]
final class WireRentalRow extends Data
{
    public function __construct(
        public readonly string $equipment,
        public readonly ?RentalStatus $status = null,
        public readonly RentalStatus|null $previous = null,
    ) {}
}
