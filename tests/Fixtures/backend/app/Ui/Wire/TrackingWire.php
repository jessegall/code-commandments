<?php

namespace Shop\Ui\Wire;

/**
 * A tracking reference in its wire form — the field {@see ShipmentWire} asks to serialize itself.
 * Two scalar fields, so the whole clump is a value the projection rule can vouch for.
 */
final class TrackingWire
{
    public function __construct(
        public readonly string $code,
        public readonly string $url,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toWire(): array
    {
        return ['code' => $this->code, 'url' => $this->url];
    }
}
