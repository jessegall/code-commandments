<?php

namespace Shop\Scales;

/**
 * An optional tare capability some devices and plugins offer and others lack.
 */
interface Tares
{
    public function tare(): string;
}
