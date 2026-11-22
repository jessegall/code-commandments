<?php

namespace Illuminate\Routing;

trait FiltersControllerMiddleware
{
    public static function methodExcludedByOptions($method, array $options) {}
}
