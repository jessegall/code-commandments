<?php

namespace Illuminate\Routing;

trait FiltersControllerMiddleware
{
    /** @return bool */
    public static function methodExcludedByOptions($method, array $options) {}
}
