<?php

namespace Shop\Greenhouse;

use JesseGall\CodeCommandments\Sins\Backend\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Harvest\CropCalendar;

final readonly class VentSchedule
{
    #[Sinful(ConcreteCrossModuleDependency::class)]
    public function __construct(private CropCalendar $calendar, private int $openingHour = 7) {}

    public function openingHourOn(int $day): int
    {
        $ripe = count($this->calendar->bedsRipeOn($day));

        return match (true) {
            $ripe === 0 => $this->openingHour + 2,
            $ripe > 10 => max(5, $this->openingHour - 2),
            default => $this->openingHour,
        };
    }

    public function staysShut(int $day, float $outsideCelsius): bool
    {
        return $outsideCelsius < 4.5 || $this->openingHourOn($day) > 9;
    }
}
