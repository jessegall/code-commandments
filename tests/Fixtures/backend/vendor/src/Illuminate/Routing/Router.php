<?php

namespace Illuminate\Routing;

class Router implements \Illuminate\Contracts\Routing\BindingRegistrar, \Illuminate\Contracts\Routing\Registrar
{
    use \Illuminate\Support\Traits\Macroable, \Illuminate\Support\Traits\Tappable;
    protected $events = NULL;
    protected $container = NULL;
    protected $routes = NULL;
    protected $current = NULL;
    protected $currentRequest = NULL;
    protected $middleware = array (
);
    protected $middlewareGroups = array (
);
    public $middlewarePriority = array (
);
    protected $binders = array (
);
    protected $patterns = array (
);
    protected $groupStack = array (
);
    protected $implicitBindingCallback = NULL;
    public static $verbs = array (
  0 => 'GET',
  1 => 'HEAD',
  2 => 'POST',
  3 => 'PUT',
  4 => 'PATCH',
  5 => 'DELETE',
  6 => 'OPTIONS',
);
    public function __construct(\Illuminate\Contracts\Events\Dispatcher $events, ?\Illuminate\Container\Container $container = NULL) {}
    public function get($uri, $action = NULL) {}
    public function post($uri, $action = NULL) {}
    public function put($uri, $action = NULL) {}
    public function patch($uri, $action = NULL) {}
    public function delete($uri, $action = NULL) {}
    public function options($uri, $action = NULL) {}
    public function any($uri, $action = NULL) {}
    public function fallback($action) {}
    public function redirect($uri, $destination, $status = 302) {}
    public function permanentRedirect($uri, $destination) {}
    public function view($uri, $view, $data = array (
), $status = 200, array $headers = array (
)) {}
    public function match($methods, $uri, $action = NULL) {}
    public function resources(array $resources, array $options = array (
)) {}
    public function softDeletableResources(array $resources, array $options = array (
)) {}
    public function resource($name, $controller, array $options = array (
)) {}
    public function apiResources(array $resources, array $options = array (
)) {}
    public function apiResource($name, $controller, array $options = array (
)) {}
    public function singletons(array $singletons, array $options = array (
)) {}
    public function singleton($name, $controller, array $options = array (
)) {}
    public function apiSingletons(array $singletons, array $options = array (
)) {}
    public function apiSingleton($name, $controller, array $options = array (
)) {}
    public function group(array $attributes, $routes) {}
    protected function updateGroupStack(array $attributes) {}
    public function mergeWithLastGroup($new, $prependExistingPrefix = true) {}
    protected function loadRoutes($routes) {}
    public function getLastGroupPrefix() {}
    public function addRoute($methods, $uri, $action) {}
    protected function createRoute($methods, $uri, $action) {}
    protected function actionReferencesController($action) {}
    protected function convertToControllerAction($action) {}
    protected function prependGroupNamespace($class) {}
    protected function prependGroupController($class) {}
    public function newRoute($methods, $uri, $action) {}
    protected function prefix($uri) {}
    protected function addWhereClausesToRoute($route) {}
    protected function mergeGroupAttributesIntoRoute($route) {}
    public function respondWithRoute($name) {}
    public function dispatch(\Illuminate\Http\Request $request) {}
    public function dispatchToRoute(\Illuminate\Http\Request $request) {}
    protected function findRoute($request) {}
    protected function runRoute(\Illuminate\Http\Request $request, \Illuminate\Routing\Route $route) {}
    protected function runRouteWithinStack(\Illuminate\Routing\Route $route, \Illuminate\Http\Request $request) {}
    public function gatherRouteMiddleware(\Illuminate\Routing\Route $route) {}
    public function resolveMiddleware(array $middleware, array $excluded = array (
)) {}
    protected function sortMiddleware(\Illuminate\Support\Collection $middlewares) {}
    public function prepareResponse($request, $response) {}
    public static function toResponse($request, $response) {}
    public function substituteBindings($route) {}
    public function substituteImplicitBindings($route) {}
    public function substituteImplicitBindingsUsing($callback) {}
    protected function performBinding($key, $value, $route) {}
    public function matched($callback) {}
    public function getMiddleware() {}
    public function aliasMiddleware($name, $class) {}
    public function hasMiddlewareGroup($name) {}
    public function getMiddlewareGroups() {}
    public function middlewareGroup($name, array $middleware) {}
    public function prependMiddlewareToGroup($group, $middleware) {}
    public function pushMiddlewareToGroup($group, $middleware) {}
    public function removeMiddlewareFromGroup($group, $middleware) {}
    public function flushMiddlewareGroups() {}
    public function bind($key, $binder) {}
    public function model($key, $class, ?\Closure $callback = NULL) {}
    public function getBindingCallback($key) {}
    public function getPatterns() {}
    public function pattern($key, $pattern) {}
    public function patterns($patterns) {}
    public function hasGroupStack() {}
    public function getGroupStack() {}
    public function input($key, $default = NULL) {}
    public function getCurrentRequest() {}
    public function getCurrentRoute() {}
    public function current() {}
    public function has($name) {}
    public function currentRouteName() {}
    public function is(...$patterns) {}
    public function currentRouteNamed(...$patterns) {}
    public function currentRouteAction() {}
    public function uses(...$patterns) {}
    public function currentRouteUses($action) {}
    public function singularResourceParameters($singular = true) {}
    public function resourceParameters(array $parameters = array (
)) {}
    public function resourceVerbs(array $verbs = array (
)) {}
    public function getRoutes() {}
    public function setRoutes(\Illuminate\Routing\RouteCollection $routes) {}
    public function setCompiledRoutes(array $routes) {}
    public static function uniqueMiddleware(array $middleware) {}
    public function setContainer(\Illuminate\Container\Container $container) {}
    public function __call($method, $parameters) {}
    public function macroCall($method, $parameters) {}
}
