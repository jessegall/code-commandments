<?php

namespace Shop\Catalog;

/** A keyed store of per-code catalog entries. */
final class SkuRegistry
{
    public function has(string $code): bool
    {
        return $code !== '';
    }

    public function get(string $code): SkuEntry
    {
        return new SkuEntry();
    }
}
