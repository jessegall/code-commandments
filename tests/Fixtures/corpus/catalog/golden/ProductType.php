<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * The closed set of product kinds the catalog can hold.
 */
enum ProductType: string
{
    /** A tangible item that ships and decrements stock on sale. */
    case Physical = 'physical';

    /** A downloadable file with no shipping and unlimited stock. */
    case Digital = 'digital';

    /** A recurring access grant billed on a cycle, never shipped. */
    case Subscription = 'subscription';

    public function isShippable(): bool
    {
        return match ($this) {
            ProductType::Physical => true,
            ProductType::Digital, ProductType::Subscription => false,
        };
    }

    public function tracksStock(): bool
    {
        return match ($this) {
            ProductType::Physical => true,
            ProductType::Digital, ProductType::Subscription => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            ProductType::Physical => 'Physical good',
            ProductType::Digital => 'Digital download',
            ProductType::Subscription => 'Subscription',
        };
    }
}
