<?php

namespace Shop\Http\Pages\Hydration;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\DerivedCollectionCast;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/*
 * N2 scenario 4 — the derivation written through a COLLECTION: `->map(factory)->values()->all()` fills the slot the
 * class already declares as a collection of that element, the same work `array_map` does.
 */
final class CatalogRibbons extends Data
{
    public function __construct(
        #[DataCollectionOf(ProductBadge::class)]
        public readonly array $ribbons,
    ) {}
}

final class CatalogRibbonsComposer
{
    /**
     * @param  Collection<int, string>  $skus
     */
    #[Sinful(DerivedCollectionCast::class)]
    public function compose(Collection $skus): CatalogRibbons
    {
        return CatalogRibbons::from(['ribbons' => $skus->map(ProductBadge::forProduct(...))->values()->all()]);
    }

    /**
     * @param  Collection<int, string>  $skus
     */
    #[Righteous(DerivedCollectionCast::class)]
    public function preview(Collection $skus): Collection
    {
        // The mapped collection is handed back as a collection, not poured into a Data slot.
        return $skus->map(ProductBadge::forProduct(...))->values();
    }
}
