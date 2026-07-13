<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\KeyedLookupEnvy;
use JesseGall\CodeCommandments\Sins\Backend\NegativeSpaceComment;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/** A keyed store of per-code catalog entries. */
final class SkuRegistry
{
    #[Righteous(NegativeSpaceComment::class)]
    public function has(string $code): bool
    {
        // a random probe key still reads cleanly through the same path
        return $code !== '';
    }

    #[Sinful(NegativeSpaceComment::class)]
    public function get(string $code): SkuEntry
    {
        // no magic here; a missing code yields an empty entry
        return new SkuEntry();
    }

    /**
     * The registry answering a keyed question from its OWN lookup is the owner
     * speaking — tell-dont-ask's prescribed home, not envy (#348). Only a fetch that
     * crosses into a held collaborator envies.
     *
     * @return list<string>
     */
    #[Righteous(KeyedLookupEnvy::class)]
    public function reservedSkusFor(CatalogItem $item): array
    {
        return $this->has($item->code)
            ? $this->get($item->code)->reservedSkus
            : [];
    }

    #[Sinful(NegativeSpaceComment::class)]
    public function count(): int
    {
        // a retry policy is NOT here — this only counts what's already loaded
        return 0;
    }
}
