<?php

namespace Shop\Steps;

/**
 * One step of a checkout run, giving the line it prints.
 */
interface RunStep
{
    public function handle(): string;
}
