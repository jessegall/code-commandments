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
    /** @return \Illuminate\Routing\Route */
    public function get($uri, $action = NULL) {}
    /** @return \Illuminate\Routing\Route */
    public function post($uri, $action = NULL) {}
    /** @return \Illuminate\Routing\Route */
    public function put($uri, $action = NULL) {}
    /** @return \Illuminate\Routing\Route */
    public function patch($uri, $action = NULL) {}
    /** @return \Illuminate\Routing\Route */
    public function delete($uri, $action = NULL) {}
    /** @return \Illuminate\Routing\Route */
    public function options($uri, $action = NULL) {}
    /** @return \Illuminate\Routing\Route */
    public function any($uri, $action = NULL) {}
    /** @return \Illuminate\Routing\Route */
    public function fallback($action) {}
    /** @return \Illuminate\Routing\Route */
    public function redirect($uri, $destination, $status = 302) {}
    /** @return \Illuminate\Routing\Route */
    public function permanentRedirect($uri, $destination) {}
    /** @return \Illuminate\Routing\Route */
    public function view($uri, $view, $data = array (
), $status = 200, array $headers = array (
)) {}
    /** @return \Illuminate\Routing\Route */
    public function match($methods, $uri, $action = NULL) {}
    /** @return void */
    public function resources(array $resources, array $options = array (
)) {}
    /** @return void */
    public function softDeletableResources(array $resources, array $options = array (
)) {}
    /** @return \Illuminate\Routing\PendingResourceRegistration */
    public function resource($name, $controller, array $options = array (
)) {}
    /** @return void */
    public function apiResources(array $resources, array $options = array (
)) {}
    /** @return \Illuminate\Routing\PendingResourceRegistration */
    public function apiResource($name, $controller, array $options = array (
)) {}
    /** @return void */
    public function singletons(array $singletons, array $options = array (
)) {}
    /** @return \Illuminate\Routing\PendingSingletonResourceRegistration */
    public function singleton($name, $controller, array $options = array (
)) {}
    /** @return void */
    public function apiSingletons(array $singletons, array $options = array (
)) {}
    /** @return \Illuminate\Routing\PendingSingletonResourceRegistration */
    public function apiSingleton($name, $controller, array $options = array (
)) {}
    /** @return $this */
    public function group(array $attributes, $routes) {}
    /** @return void */
    protected function updateGroupStack(array $attributes) {}
    /** @return array */
    public function mergeWithLastGroup($new, $prependExistingPrefix = true) {}
    /** @return void */
    protected function loadRoutes($routes) {}
    /** @return string */
    public function getLastGroupPrefix() {}
    /** @return \Illuminate\Routing\Route */
    public function addRoute($methods, $uri, $action) {}
    /** @return \Illuminate\Routing\Route */
    protected function createRoute($methods, $uri, $action) {}
    /** @return bool */
    protected function actionReferencesController($action) {}
    /** @return array */
    protected function convertToControllerAction($action) {}
    /** @return string */
    protected function prependGroupNamespace($class) {}
    /** @return string */
    protected function prependGroupController($class) {}
    /** @return \Illuminate\Routing\Route */
    public function newRoute($methods, $uri, $action) {}
    /** @return string */
    protected function prefix($uri) {}
    /** @return \Illuminate\Routing\Route */
    protected function addWhereClausesToRoute($route) {}
    /** @return void */
    protected function mergeGroupAttributesIntoRoute($route) {}
    /** @return \Symfony\Component\HttpFoundation\Response */
    public function respondWithRoute($name) {}
    /** @return \Symfony\Component\HttpFoundation\Response */
    public function dispatch(\Illuminate\Http\Request $request) {}
    /** @return \Symfony\Component\HttpFoundation\Response */
    public function dispatchToRoute(\Illuminate\Http\Request $request) {}
    /** @return \Illuminate\Routing\Route */
    protected function findRoute($request) {}
    /** @return \Symfony\Component\HttpFoundation\Response */
    protected function runRoute(\Illuminate\Http\Request $request, \Illuminate\Routing\Route $route) {}
    /** @return mixed */
    protected function runRouteWithinStack(\Illuminate\Routing\Route $route, \Illuminate\Http\Request $request) {}
    /** @return array */
    public function gatherRouteMiddleware(\Illuminate\Routing\Route $route) {}
    /** @return array */
    public function resolveMiddleware(array $middleware, array $excluded = array (
)) {}
    /** @return array */
    protected function sortMiddleware(\Illuminate\Support\Collection $middlewares) {}
    /** @return \Symfony\Component\HttpFoundation\Response */
    public function prepareResponse($request, $response) {}
    /** @return \Symfony\Component\HttpFoundation\Response */
    public static function toResponse($request, $response) {}
    /** @return \Illuminate\Routing\Route */
    public function substituteBindings($route) {}
    /** @return void */
    public function substituteImplicitBindings($route) {}
    /** @return $this */
    public function substituteImplicitBindingsUsing($callback) {}
    /** @return mixed */
    protected function performBinding($key, $value, $route) {}
    /** @return void */
    public function matched($callback) {}
    /** @return array */
    public function getMiddleware() {}
    /** @return $this */
    public function aliasMiddleware($name, $class) {}
    /** @return bool */
    public function hasMiddlewareGroup($name) {}
    /** @return array */
    public function getMiddlewareGroups() {}
    /** @return $this */
    public function middlewareGroup($name, array $middleware) {}
    /** @return $this */
    public function prependMiddlewareToGroup($group, $middleware) {}
    /** @return $this */
    public function pushMiddlewareToGroup($group, $middleware) {}
    /** @return $this */
    public function removeMiddlewareFromGroup($group, $middleware) {}
    /** @return $this */
    public function flushMiddlewareGroups() {}
    /** @return void */
    public function bind($key, $binder) {}
    /** @return void */
    public function model($key, $class, ?\Closure $callback = NULL) {}
    /** @return \Closure|null */
    public function getBindingCallback($key) {}
    /** @return array */
    public function getPatterns() {}
    /** @return void */
    public function pattern($key, $pattern) {}
    /** @return void */
    public function patterns($patterns) {}
    /** @return bool */
    public function hasGroupStack() {}
    /** @return array */
    public function getGroupStack() {}
    /** @return mixed */
    public function input($key, $default = NULL) {}
    /** @return \Illuminate\Http\Request */
    public function getCurrentRequest() {}
    /** @return \Illuminate\Routing\Route|null */
    public function getCurrentRoute() {}
    /** @return \Illuminate\Routing\Route|null */
    public function current() {}
    /** @return bool */
    public function has($name) {}
    /** @return string|null */
    public function currentRouteName() {}
    /** @return bool */
    public function is(...$patterns) {}
    /** @return bool */
    public function currentRouteNamed(...$patterns) {}
    /** @return string|null */
    public function currentRouteAction() {}
    /** @return bool */
    public function uses(...$patterns) {}
    /** @return bool */
    public function currentRouteUses($action) {}
    /** @return void */
    public function singularResourceParameters($singular = true) {}
    /** @return void */
    public function resourceParameters(array $parameters = array (
)) {}
    /** @return array|null */
    public function resourceVerbs(array $verbs = array (
)) {}
    /** @return \Illuminate\Routing\RouteCollectionInterface */
    public function getRoutes() {}
    /** @return void */
    public function setRoutes(\Illuminate\Routing\RouteCollection $routes) {}
    /** @return void */
    public function setCompiledRoutes(array $routes) {}
    /** @return array */
    public static function uniqueMiddleware(array $middleware) {}
    /** @return $this */
    public function setContainer(\Illuminate\Container\Container $container) {}
    /** @return mixed */
    public function __call($method, $parameters) {}
    /** @return mixed */
    public function macroCall($method, $parameters) {}
}
