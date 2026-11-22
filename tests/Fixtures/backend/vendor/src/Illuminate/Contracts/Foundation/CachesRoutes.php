<?php

namespace Illuminate\Contracts\Foundation;

interface CachesRoutes
{
    /** @return bool */
    public function routesAreCached();
    /** @return string */
    public function getCachedRoutesPath();
}
