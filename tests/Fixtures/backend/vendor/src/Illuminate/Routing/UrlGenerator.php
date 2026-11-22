<?php

namespace Illuminate\Routing;

class UrlGenerator implements \Illuminate\Contracts\Routing\UrlGenerator
{
    use \Illuminate\Support\InteractsWithTime, \Illuminate\Support\Traits\Macroable;
    protected $routes = NULL;
    protected $request = NULL;
    protected $assetRoot = NULL;
    protected $forcedRoot = NULL;
    protected $forceScheme = NULL;
    protected $cachedRoot = NULL;
    protected $cachedScheme = NULL;
    protected $rootNamespace = NULL;
    protected $sessionResolver = NULL;
    protected $keyResolver = NULL;
    protected $missingNamedRouteResolver = NULL;
    protected $formatHostUsing = NULL;
    protected $formatPathUsing = NULL;
    protected $routeGenerator = NULL;
    public function __construct(\Illuminate\Routing\RouteCollectionInterface $routes, \Illuminate\Http\Request $request, $assetRoot = NULL) {}
    /** @return string */
    public function full() {}
    /** @return string */
    public function current() {}
    /** @return string */
    public function previous($fallback = false) {}
    /** @return string */
    public function previousPath($fallback = false) {}
    /** @return string|null */
    protected function getPreviousUrlFromSession() {}
    /** @return string */
    public function to($path, $extra = array (
), $secure = NULL) {}
    /** @return string */
    public function query($path, $query = array (
), $extra = array (
), $secure = NULL) {}
    /** @return string */
    public function secure($path, $parameters = array (
)) {}
    /** @return string */
    public function asset($path, $secure = NULL) {}
    /** @return string */
    public function secureAsset($path) {}
    /** @return string */
    public function assetFrom($root, $path, $secure = NULL) {}
    /** @return string */
    protected function removeIndex($root) {}
    /** @return string */
    public function formatScheme($secure = NULL) {}
    /** @return string */
    public function signedRoute($name, $parameters = array (
), $expiration = NULL, $absolute = true) {}
    /** @return void */
    protected function ensureSignedRouteParametersAreNotReserved($parameters) {}
    /** @return string */
    public function temporarySignedRoute($name, $expiration, $parameters = array (
), $absolute = true) {}
    /** @return bool */
    public function hasValidSignature(\Illuminate\Http\Request $request, $absolute = true, \Closure|array $ignoreQuery = array (
)) {}
    /** @return bool */
    public function hasValidRelativeSignature(\Illuminate\Http\Request $request, \Closure|array $ignoreQuery = array (
)) {}
    /** @return bool */
    public function hasCorrectSignature(\Illuminate\Http\Request $request, $absolute = true, \Closure|array $ignoreQuery = array (
)) {}
    /** @return bool */
    public function signatureHasNotExpired(\Illuminate\Http\Request $request) {}
    /** @return string */
    public function route($name, $parameters = array (
), $absolute = true) {}
    /** @return string */
    public function toRoute($route, $parameters, $absolute) {}
    /** @return string */
    public function action($action, $parameters = array (
), $absolute = true) {}
    /** @return string */
    protected function formatAction($action) {}
    /** @return array */
    public function formatParameters($parameters) {}
    /** @return array */
    protected function extractQueryString($path) {}
    /** @return string */
    public function formatRoot($scheme, $root = NULL) {}
    /** @return string */
    public function format($root, $path, $route = NULL) {}
    /** @return bool */
    public function isValidUrl($path) {}
    /** @return \Illuminate\Routing\RouteUrlGenerator */
    protected function routeUrl() {}
    /** @return void */
    public function defaults(array $defaults) {}
    /** @return array */
    public function getDefaultParameters() {}
    /** @return void */
    public function forceScheme($scheme) {}
    /** @return void */
    public function forceHttps($force = true) {}
    /** @return void */
    public function useOrigin(?string $root) {}
    /** @return void */
    public function forceRootUrl($root) {}
    /** @return void */
    public function useAssetOrigin(?string $root) {}
    /** @return $this */
    public function formatHostUsing(\Closure $callback) {}
    /** @return $this */
    public function formatPathUsing(\Closure $callback) {}
    /** @return \Closure */
    public function pathFormatter() {}
    /** @return \Illuminate\Http\Request */
    public function getRequest() {}
    /** @return void */
    public function setRequest(\Illuminate\Http\Request $request) {}
    /** @return $this */
    public function setRoutes(\Illuminate\Routing\RouteCollectionInterface $routes) {}
    /** @return \Illuminate\Session\Store|null */
    protected function getSession() {}
    /** @return $this */
    public function setSessionResolver(callable $sessionResolver) {}
    /** @return $this */
    public function setKeyResolver(callable $keyResolver) {}
    /** @return \Illuminate\Routing\UrlGenerator */
    public function withKeyResolver(callable $keyResolver) {}
    /** @return $this */
    public function resolveMissingNamedRoutesUsing(callable $missingNamedRouteResolver) {}
    /** @return string */
    public function getRootControllerNamespace() {}
    /** @return $this */
    public function setRootControllerNamespace($rootNamespace) {}
}
