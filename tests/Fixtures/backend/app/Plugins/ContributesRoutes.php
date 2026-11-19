<?php

namespace Shop\Plugins;

/**
 * An optional routeCount capability some devices and plugins offer and others lack.
 */
interface ContributesRoutes
{
    public function routeCount(): int;
}
