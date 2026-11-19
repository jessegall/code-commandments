<?php

namespace Shop\Printing;

/**
 * An optional cut capability some devices and plugins offer and others lack.
 */
interface CutsPaper
{
    public function cut(): bool;
}
