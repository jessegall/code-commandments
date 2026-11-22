<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Prints the stickers of a pallet layer: a mapper that turns the layer's cartons into another shape, a sticker per
 * carton, which the layer itself must not know about.
 */
final class LayerStickers
{
    /**
     * @return array<string, string>
     */
    #[Righteous(FeatureEnvy::class)]
    public function keyed(PalletLayer $layer): array
    {
        $stickers = [];
        foreach ($layer->cartons as $carton) {
            $stickers[$carton] = sprintf('%s/%s', $layer->reference, $carton);
        }

        return $stickers;
    }

    /**
     * @return list<string>
     */
    #[Righteous(FeatureEnvy::class)]
    public function listed(PalletLayer $layer): array
    {
        return array_map(static fn (string $carton) => "{$layer->reference}/{$carton}", $layer->cartons);
    }
}
