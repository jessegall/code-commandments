<?php

namespace Shop\Irrigation;

abstract class MoistureSensor
{
    abstract public function percentAt(string $bed): int;
}
