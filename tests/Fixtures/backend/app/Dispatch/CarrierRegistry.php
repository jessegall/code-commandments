<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * A generic keyed store behind a typed façade — the righteous twin of a derived argument. `add()` takes
 * the subject once and derives the key itself, and the inner `register($carrier->key, $carrier)` only
 * looks like the sin: `register` accepts `mixed`, so it provably cannot derive anything from what it
 * holds, and the key beside the item is the only way the value gets there (#504).
 */
#[Righteous]
final class CarrierRegistry
{
    /**
     * @var array<string, Carrier>
     */
    private array $carriers = [];

    public function add(Carrier $carrier): static
    {
        return $this->register($carrier->key, $carrier);
    }

    public function get(string $key): Carrier
    {
        return $this->carriers[$key] ?? throw new UnknownCarrier($key);
    }

    /**
     * The generic half: a store that knows nothing about what it stores.
     */
    private function register(string $key, mixed $item): static
    {
        $this->carriers[$key] = $item;

        return $this;
    }
}
