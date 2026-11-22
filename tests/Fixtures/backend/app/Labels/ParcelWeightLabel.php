<?php

namespace Shop\Labels;

use Shop\Support\Concerns\FormatsWeights;

/**
 * The weight line printed on a parcel label.
 */
final class ParcelWeightLabel
{
    use FormatsWeights;

    public function line(int $grams): string
    {
        return 'Weight: ' . $this->weightLabel($grams, 'kg');
    }
}
