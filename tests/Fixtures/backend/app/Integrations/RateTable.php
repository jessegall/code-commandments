<?php

namespace Shop\Integrations;

use JesseGall\CodeCommandments\Sins\Backend\RawDecodedArrayReturn;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * The shape the wire actually has, declared. A decoded response handed back raw asks every caller
 * to know the payload's keys; this states them once, at the boundary that read them.
 */
#[Fixed(RawDecodedArrayReturn::class)]
final class RateTable
{
    /**
     * @param  array<string, float>  $rates
     */
    public function __construct(
        public readonly string $base,
        public readonly array $rates,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            base: (string) $payload['base'],
            rates: $payload['rates'],
        );
    }
}
