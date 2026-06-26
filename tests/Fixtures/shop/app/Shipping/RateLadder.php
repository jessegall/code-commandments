<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Detectors\Backend\IfElseLadderDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Picks a shipping band through a four-rung if/elseif ladder — a closed set of
 * weight tiers decided by hand instead of a table or a match.
 */
final class RateLadder
{
    #[Sinful(IfElseLadderDetector::class)]
    public function band(int $grams): string
    {
        if ($grams < 250) {
            return 'letter';
        } elseif ($grams < 2_000) {
            return 'parcel-s';
        } elseif ($grams < 10_000) {
            return 'parcel-m';
        } else {
            return 'parcel-l';
        }
    }

    public function isHeavy(int $grams): bool
    {
        if ($grams > 10_000) {
            return true;
        } else {
            return false;
        }
    }

    public function surchargeCents(int $grams): int
    {
        return $this->isHeavy($grams) ? 500 : 0;
    }

    public function volumetric(int $grams, int $litres): int
    {
        return max($grams, $litres * 200);
    }
}
