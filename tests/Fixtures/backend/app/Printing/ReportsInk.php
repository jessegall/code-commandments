<?php

namespace Shop\Printing;

/**
 * An optional inkLow capability some devices and plugins offer and others lack.
 */
interface ReportsInk
{
    public function inkLow(): bool;
}
