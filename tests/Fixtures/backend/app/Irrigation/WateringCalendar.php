<?php

namespace Shop\Irrigation;

use JesseGall\CodeCommandments\Sins\Backend\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Fixed;

#[Fixed(ConcreteCrossModuleDependency::class)]
interface WateringCalendar
{
    public function litresDueOn(string $bed, int $day): int;
}
