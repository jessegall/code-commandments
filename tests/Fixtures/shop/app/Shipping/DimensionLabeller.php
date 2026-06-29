<?php

namespace Shop\Shipping;

/**
 * Righteous twin for FeatureEnvyDetector: pure formatting of the dimensions into a
 * label — no decision over them — so presentation legitimately lives here, off the
 * data object. It must NOT be flagged.
 */
final class DimensionLabeller
{
    public function label(ParcelDimensions $dimensions): string
    {
        return sprintf(
            '%dg %d×%d×%dmm',
            $dimensions->grams,
            $dimensions->lengthMm,
            $dimensions->widthMm,
            $dimensions->heightMm,
        );
    }
}
