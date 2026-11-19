<?php

namespace Shop\Harvest;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Irrigation\WaterSchedule;

final class HarvestPlanner
{
    #[Sinful(ConcreteCrossModuleDependency::class)]
    public function __construct(
        private CropCalendar $crops,
        private WaterSchedule $water,
    ) {}

    /**
     * @return array<string, int>
     */
    public function wateringBeforePicking(int $day): array
    {
        $litres = [];
        foreach ($this->crops->bedsRipeOn($day) as $bed) {
            $litres[$bed] = $this->water->litresDueOn($bed, $day - 1);
        }

        return $litres;
    }
}
