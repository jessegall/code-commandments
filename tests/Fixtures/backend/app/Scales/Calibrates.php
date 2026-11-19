<?php

namespace Shop\Scales;

/**
 * An optional calibrate capability some devices and plugins offer and others lack.
 */
interface Calibrates
{
    public function calibrate(): string;
}
