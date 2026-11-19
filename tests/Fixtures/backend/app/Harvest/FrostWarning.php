<?php

namespace Shop\Harvest;

use JesseGall\CodeCommandments\Sins\Backend\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Irrigation\SoilReading;

final class FrostWarning
{
    #[Righteous(ConcreteCrossModuleDependency::class)]
    public function __construct(private SoilReading $reading) {}

    public function coverBedTonight(int $forecastCelsius): bool
    {
        return $forecastCelsius < 2 && !$this->reading->isDry();
    }
}
