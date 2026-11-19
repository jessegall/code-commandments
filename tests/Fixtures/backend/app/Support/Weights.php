<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\FalseForNothing;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Parcel weights as couriers write them, in grams.
 */
final class Weights
{
    #[Sinful(FalseForNothing::class)]
    public static function gramsIn(string $label): int|float|false
    {
        if (preg_match('/^(\d+(?:\.\d+)?)\s*kg$/', $label, $match) !== 1) {
            return false;
        }

        return (float) $match[1] * 1000;
    }
}
