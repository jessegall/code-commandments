<?php

namespace Shop\Irrigation;

use Illuminate\Database\ConnectionInterface;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Fixed;

final class WaterSchedule implements WateringCalendar
{
    public function __construct(private ConnectionInterface $database) {}

    #[Fixed(ConcreteCrossModuleDependency::class)]
    public function litresDueOn(string $bed, int $day): int
    {
        return (int) $this->database->table('watering')->where('bed', $bed)->where('day', $day)->sum('litres');
    }
}
