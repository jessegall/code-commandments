<?php

namespace Shop\Integrations;

use JesseGall\CodeCommandments\Sins\Backend\RawDecodedArrayReturn;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * A ROUND-TRIP, not a boundary decode: the payload is ours, encoded and read straight back to obtain
 * its serialized form as data to walk. Nothing crossed in, so there is no untyped input to name — and
 * the shape being walked is deliberately "whatever this serializes to", which no type can promise.
 */
final class PayloadSnapshot
{

    /**
     * @return array<string, mixed>
     */
    #[Righteous(RawDecodedArrayReturn::class)]
    public function wireShapeOf(RateTable $table): array
    {
        return json_decode((string) json_encode($table, JSON_PRESERVE_ZERO_FRACTION), true);
    }

}
