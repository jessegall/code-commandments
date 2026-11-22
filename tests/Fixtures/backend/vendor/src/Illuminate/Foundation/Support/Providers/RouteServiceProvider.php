<?php

namespace Illuminate\Foundation\Support\Providers;

class RouteServiceProvider extends \Illuminate\Support\ServiceProvider
{
    use \Illuminate\Support\Traits\ForwardsCalls;
    protected $namespace = NULL;
    protected $loadRoutesUsing = NULL;
    protected static $alwaysLoadRoutesUsing = NULL;
    protected static $alwaysLoadCachedRoutesUsing = NULL;
    /** @return void */
    public function register() {}
    /** @return void */
    public function boot() {}
    /** @return $this */
    protected function routes(\Closure $routesCallback) {}
    /** @return void */
    public static function loadRoutesUsing(?\Closure $routesCallback) {}
    /** @return void */
    public static function loadCachedRoutesUsing(?\Closure $routesCallback) {}
    /** @return void */
    protected function setRootControllerNamespace() {}
    /** @return bool */
    protected function routesAreCached() {}
    /** @return void */
    protected function loadCachedRoutes() {}
    /** @return void */
    protected function loadRoutes() {}
    /** @return mixed */
    public function __call($method, $parameters) {}
}
