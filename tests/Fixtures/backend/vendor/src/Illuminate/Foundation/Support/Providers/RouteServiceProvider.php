<?php

namespace Illuminate\Foundation\Support\Providers;

class RouteServiceProvider extends \Illuminate\Support\ServiceProvider
{
    use \Illuminate\Support\Traits\ForwardsCalls;
    protected $namespace = NULL;
    protected $loadRoutesUsing = NULL;
    protected static $alwaysLoadRoutesUsing = NULL;
    protected static $alwaysLoadCachedRoutesUsing = NULL;
    public function register() {}
    public function boot() {}
    protected function routes(\Closure $routesCallback) {}
    public static function loadRoutesUsing(?\Closure $routesCallback) {}
    public static function loadCachedRoutesUsing(?\Closure $routesCallback) {}
    protected function setRootControllerNamespace() {}
    protected function routesAreCached() {}
    protected function loadCachedRoutes() {}
    protected function loadRoutes() {}
    public function __call($method, $parameters) {}
}
