<?php

namespace Illuminate\Routing;

class Route
{
    use \Illuminate\Support\Traits\Conditionable, \Illuminate\Routing\CreatesRegularExpressionRouteConstraints, \Illuminate\Routing\FiltersControllerMiddleware, \Illuminate\Support\Traits\Macroable, \Illuminate\Routing\ResolvesRouteDependencies;
    public $uri = NULL;
    public $methods = NULL;
    public $action = NULL;
    public $isFallback = false;
    public $controller = NULL;
    public $defaults = array (
);
    public $wheres = array (
);
    public $parameters = NULL;
    public $parameterNames = NULL;
    protected $originalParameters = NULL;
    protected $withTrashedBindings = false;
    protected $lockSeconds = NULL;
    protected $waitSeconds = NULL;
    public $computedMiddleware = NULL;
    public $compiled = NULL;
    protected $router = NULL;
    protected $container = NULL;
    protected $bindingFields = array (
);
    public static $validators = NULL;
    public function __construct($methods, $uri, $action) {}
    protected function parseAction($action) {}
    public function run() {}
    protected function isControllerAction() {}
    protected function runCallable() {}
    protected function isSerializedClosure() {}
    protected function runController() {}
    public function getController() {}
    public function getControllerClass() {}
    protected function getControllerMethod() {}
    protected function parseControllerCallback() {}
    public function flushController() {}
    public function matches(\Illuminate\Http\Request $request, $includingMethod = true) {}
    protected function compileRoute() {}
    public function bind(\Illuminate\Http\Request $request) {}
    public function hasParameters() {}
    public function hasParameter($name) {}
    public function parameter($name, $default = NULL) {}
    public function originalParameter($name, $default = NULL) {}
    public function setParameter($name, $value) {}
    public function forgetParameter($name) {}
    public function parameters() {}
    public function originalParameters() {}
    public function parametersWithoutNulls() {}
    public function parameterNames() {}
    protected function compileParameterNames() {}
    public function signatureParameters($conditions = array (
)) {}
    public function bindingFieldFor($parameter) {}
    public function bindingFields() {}
    public function setBindingFields(array $bindingFields) {}
    public function parentOfParameter($parameter) {}
    public function withTrashed($withTrashed = true) {}
    public function allowsTrashedBindings() {}
    public function defaults($key, $value) {}
    public function setDefaults(array $defaults) {}
    public function where($name, $expression = NULL) {}
    protected function parseWhere($name, $expression) {}
    public function setWheres(array $wheres) {}
    public function fallback() {}
    public function setFallback($isFallback) {}
    public function methods() {}
    public function httpOnly() {}
    public function httpsOnly() {}
    public function secure() {}
    public function domain($domain = NULL) {}
    public function getDomain() {}
    public function getPrefix() {}
    public function prefix($prefix) {}
    protected function updatePrefixOnAction($prefix) {}
    public function uri() {}
    public function setUri($uri) {}
    protected function parseUri($uri) {}
    public function getName() {}
    public function name($name) {}
    public function named(...$patterns) {}
    public function uses($action) {}
    protected function addGroupNamespaceToStringUses($action) {}
    public function getActionName() {}
    public function getActionMethod() {}
    public function getAction($key = NULL) {}
    public function setAction(array $action) {}
    public function getMissing() {}
    public function missing($missing) {}
    public function gatherMiddleware() {}
    public function middleware($middleware = NULL) {}
    public function can($ability, $models = array (
)) {}
    public function controllerMiddleware() {}
    protected function staticallyProvidedControllerMiddleware(string $class, string $method) {}
    public function withoutMiddleware($middleware) {}
    public function excludedMiddleware() {}
    public function scopeBindings() {}
    public function withoutScopedBindings() {}
    public function enforcesScopedBindings() {}
    public function preventsScopedBindings() {}
    public function block($lockSeconds = 10, $waitSeconds = 10) {}
    public function withoutBlocking() {}
    public function locksFor() {}
    public function waitsFor() {}
    public function controllerDispatcher() {}
    public static function getValidators() {}
    public function toSymfonyRoute() {}
    public function getOptionalParameterNames() {}
    public function getCompiled() {}
    public function setRouter(\Illuminate\Routing\Router $router) {}
    public function setContainer(\Illuminate\Container\Container $container) {}
    public function prepareForSerialization() {}
    public function __get($key) {}
}
