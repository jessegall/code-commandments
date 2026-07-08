<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DerivedCollectionCast;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/*
 * N2 scenario 2 — a derivation over a FILTERED field list through an ARROW function. The class carries its
 * own SKU state and prunes it before mapping.
 */
final class CatalogBadges extends Data
{
    public function __construct(
        #[DataCollectionOf(ProductBadge::class)]
        public readonly array $badges,
    ) {}
}

final class CatalogBadgesComposer
{
    /** @var list<string> */
    private array $skus = ['A1', 'B2', '', 'C3'];

    #[Sinful(DerivedCollectionCast::class)]
    public function compose(): CatalogBadges
    {
        $active = array_values(array_filter($this->skus, static fn (string $sku): bool => $sku !== ''));

        return CatalogBadges::from(['badges' => array_map(fn (string $sku) => ProductBadge::forProduct($sku), $active)]);
    }

    public function register(string $sku): void
    {
        $this->skus[] = $sku;
    }
}
