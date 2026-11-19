<?php

namespace Shop\Harvest;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Fixed;
use Shop\Irrigation\WateringCalendar;

final class YieldForecast
{
    #[Fixed(ConcreteCrossModuleDependency::class)]
    public function __construct(
        private CropCalendar $crops,
        private WateringCalendar $water,
    ) {}

    /**
     * @return list<string>
     */
    public function bedsByThirst(int $day): array
    {
        $beds = $this->crops->bedsRipeOn($day);
        usort($beds, fn (string $first, string $second) => $this->water->litresDueOn($second, $day) <=> $this->water->litresDueOn($first, $day));

        return $beds;
    }
}
