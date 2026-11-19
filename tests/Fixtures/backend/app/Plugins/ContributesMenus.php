<?php

namespace Shop\Plugins;

/**
 * An optional menuCount capability some devices and plugins offer and others lack.
 */
interface ContributesMenus
{
    public function menuCount(): int;
}
