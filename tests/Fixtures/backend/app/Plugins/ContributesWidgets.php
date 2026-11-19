<?php

namespace Shop\Plugins;

/**
 * An optional widgetCount capability some devices and plugins offer and others lack.
 */
interface ContributesWidgets
{
    public function widgetCount(): int;
}
