<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\CoupledFields;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The hour that rang up the most sales; a manager may correct the hour by hand when the clock was off.
 */
final class BusiestHour
{
    private int $mostSales = 0;

    private int $hour = -1;

    public function correct(int $hour): void
    {
        $this->hour = $hour;
    }

    #[Righteous(CoupledFields::class)]
    public function count(int $hour, int $sales): void
    {
        if ($sales > $this->mostSales || $this->hour < 0) {
            $this->mostSales = $sales;
            $this->hour = $hour;
        }
    }

    public function summary(): string
    {
        return "{$this->mostSales} sales at {$this->hour}";
    }
}
