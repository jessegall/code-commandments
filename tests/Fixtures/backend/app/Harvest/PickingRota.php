<?php

namespace Shop\Harvest;

use JesseGall\CodeCommandments\Sins\Backend\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Irrigation\FlowRate;
use Shop\Irrigation\MoistureSensor;

final class PickingRota
{
    #[Righteous(ConcreteCrossModuleDependency::class)]
    public function __construct(
        private CropCalendar $crops,
        private FlowRate $flow,
        private MoistureSensor $moisture,
    ) {}

    /**
     * @return list<string>
     */
    public function dryBedsFirst(int $day): array
    {
        $beds = array_filter($this->crops->bedsRipeOn($day), fn (string $bed) => $this->moisture->percentAt($bed) < 30);

        return array_values($beds);
    }

    public function minutesToSoak(int $litres): int
    {
        return $this->flow->minutesFor($litres);
    }
}
