<?php

namespace Shop\Support\Concerns;

use JesseGall\CodeCommandments\Sins\Backend\UnusedParameter;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Prints a weight in grams for a label, for every class that prints labels.
 */
trait FormatsWeights
{
    #[Sinful(UnusedParameter::class)]
    public function weightLabel(int $grams, string $unit): string
    {
        return number_format($grams / 1000, 2) . ' kg';
    }
}
