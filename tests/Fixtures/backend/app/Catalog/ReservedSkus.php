<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\KeyedLookupEnvy;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Keyed-lookup envy: uses the item's own code to fetch its entry from a registry
 * and read a fact back. The item knows its code — `$item->reservedSkus()` belongs
 * ON CatalogItem, not in a helper that treats the item as a key.
 */
final class ReservedSkus
{
    public function __construct(private readonly SkuRegistry $registry) {}

    /**
     * @return list<string>
     */
    #[Sinful(KeyedLookupEnvy::class)]
    public function forItem(CatalogItem $item): array
    {
        return $this->registry->has($item->code)
            ? $this->registry->get($item->code)->reservedSkus
            : [];
    }

    /**
     * The item knows its own reserved SKUs — ask it directly instead of using its
     * code as a key back into a registry.
     *
     * @return list<string>
     */
    #[Righteous(KeyedLookupEnvy::class)]
    public function forItemDirect(CatalogItem $item): array
    {
        return $item->reservedSkus();
    }
}
