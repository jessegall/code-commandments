<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The layers a pallet is loaded with. It walks its own layers, which are its own data to compose.
 */
final class LoadingPlan
{
    /**
     * @param  list<PalletLayer>  $layers
     */
    public function __construct(private readonly array $layers) {}

    /**
     * @return array<string, int>
     */
    #[Righteous(FeatureEnvy::class)]
    public function cartonWeights(): array
    {
        $weights = [];
        foreach ($this->layers as $layer) {
            foreach ($layer->cartons as $carton) {
                $weights[$carton] = $layer->grams;
            }
        }

        return $weights;
    }
}
