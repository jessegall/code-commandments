<?php

namespace Illuminate\Contracts\Foundation;

interface CachesConfiguration
{
    /** @return bool */
    public function configurationIsCached();
    /** @return string */
    public function getCachedConfigPath();
    /** @return string */
    public function getCachedServicesPath();
}
