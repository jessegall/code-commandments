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
    public function full() {}
    public function current() {}
    public function previous($fallback = false) {}
    public function previousPath($fallback = false) {}
    protected function getPreviousUrlFromSession() {}
    public function to($path, $extra = array (
), $secure = NULL) {}
    public function query($path, $query = array (
), $extra = array (
), $secure = NULL) {}
    public function secure($path, $parameters = array (
)) {}
    public function asset($path, $secure = NULL) {}
    public function secureAsset($path) {}
    public function assetFrom($root, $path, $secure = NULL) {}
    protected function removeIndex($root) {}
    public function formatScheme($secure = NULL) {}
    public function signedRoute($name, $parameters = array (
), $expiration = NULL, $absolute = true) {}
    protected function ensureSignedRouteParametersAreNotReserved($parameters) {}
    public function temporarySignedRoute($name, $expiration, $parameters = array (
), $absolute = true) {}
    public function hasValidSignature(\Illuminate\Http\Request $request, $absolute = true, \Closure|array $ignoreQuery = array (
)) {}
    public function hasValidRelativeSignature(\Illuminate\Http\Request $request, \Closure|array $ignoreQuery = array (
)) {}
    public function hasCorrectSignature(\Illuminate\Http\Request $request, $absolute = true, \Closure|array $ignoreQuery = array (
)) {}
    public function signatureHasNotExpired(\Illuminate\Http\Request $request) {}
    public function route($name, $parameters = array (
), $absolute = true) {}
    public function toRoute($route, $parameters, $absolute) {}
    public function action($action, $parameters = array (
), $absolute = true) {}
    protected function formatAction($action) {}
    public function formatParameters($parameters) {}
    protected function extractQueryString($path) {}
    public function formatRoot($scheme, $root = NULL) {}
    public function format($root, $path, $route = NULL) {}
    public function isValidUrl($path) {}
    protected function routeUrl() {}
    public function defaults(array $defaults) {}
    public function getDefaultParameters() {}
    public function forceScheme($scheme) {}
    public function forceHttps($force = true) {}
    public function useOrigin(?string $root) {}
    public function forceRootUrl($root) {}
    public function useAssetOrigin(?string $root) {}
    public function formatHostUsing(\Closure $callback) {}
    public function formatPathUsing(\Closure $callback) {}
    public function pathFormatter() {}
    public function getRequest() {}
    public function setRequest(\Illuminate\Http\Request $request) {}
    public function setRoutes(\Illuminate\Routing\RouteCollectionInterface $routes) {}
    protected function getSession() {}
    public function setSessionResolver(callable $sessionResolver) {}
    public function setKeyResolver(callable $keyResolver) {}
    public function withKeyResolver(callable $keyResolver) {}
    public function resolveMissingNamedRoutesUsing(callable $missingNamedRouteResolver) {}
    public function getRootControllerNamespace() {}
    public function setRootControllerNamespace($rootNamespace) {}
}
