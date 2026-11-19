<?php

namespace Shop\Printing;

/**
 * An optional duplex capability some devices and plugins offer and others lack.
 */
interface Duplexes
{
    public function duplex(): bool;
}
