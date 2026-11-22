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
    /** @return array */
    protected function parseAction($action) {}
    /** @return mixed */
    public function run() {}
    /** @return bool */
    protected function isControllerAction() {}
    /** @return mixed */
    protected function runCallable() {}
    /** @return bool */
    protected function isSerializedClosure() {}
    /** @return mixed */
    protected function runController() {}
    /** @return mixed */
    public function getController() {}
    /** @return string|null */
    public function getControllerClass() {}
    /** @return string */
    protected function getControllerMethod() {}
    /** @return array */
    protected function parseControllerCallback() {}
    /** @return void */
    public function flushController() {}
    /** @return bool */
    public function matches(\Illuminate\Http\Request $request, $includingMethod = true) {}
    /** @return \Symfony\Component\Routing\CompiledRoute */
    protected function compileRoute() {}
    /** @return $this */
    public function bind(\Illuminate\Http\Request $request) {}
    /** @return bool */
    public function hasParameters() {}
    /** @return bool */
    public function hasParameter($name) {}
    /** @return string|object|null */
    public function parameter($name, $default = NULL) {}
    /** @return string|null */
    public function originalParameter($name, $default = NULL) {}
    /** @return void */
    public function setParameter($name, $value) {}
    /** @return void */
    public function forgetParameter($name) {}
    /** @return array */
    public function parameters() {}
    /** @return array */
    public function originalParameters() {}
    /** @return array */
    public function parametersWithoutNulls() {}
    /** @return array */
    public function parameterNames() {}
    /** @return array */
    protected function compileParameterNames() {}
    /** @return array */
    public function signatureParameters($conditions = array (
)) {}
    /** @return string|null */
    public function bindingFieldFor($parameter) {}
    /** @return array */
    public function bindingFields() {}
    /** @return $this */
    public function setBindingFields(array $bindingFields) {}
    /** @return string|null */
    public function parentOfParameter($parameter) {}
    /** @return $this */
    public function withTrashed($withTrashed = true) {}
    /** @return bool */
    public function allowsTrashedBindings() {}
    /** @return $this */
    public function defaults($key, $value) {}
    /** @return $this */
    public function setDefaults(array $defaults) {}
    /** @return $this */
    public function where($name, $expression = NULL) {}
    /** @return array */
    protected function parseWhere($name, $expression) {}
    /** @return $this */
    public function setWheres(array $wheres) {}
    /** @return $this */
    public function fallback() {}
    /** @return $this */
    public function setFallback($isFallback) {}
    /** @return array */
    public function methods() {}
    /** @return bool */
    public function httpOnly() {}
    /** @return bool */
    public function httpsOnly() {}
    /** @return bool */
    public function secure() {}
    /** @return $this|string|null */
    public function domain($domain = NULL) {}
    /** @return string|null */
    public function getDomain() {}
    /** @return string|null */
    public function getPrefix() {}
    /** @return $this */
    public function prefix($prefix) {}
    /** @return void */
    protected function updatePrefixOnAction($prefix) {}
    /** @return string */
    public function uri() {}
    /** @return $this */
    public function setUri($uri) {}
    /** @return string */
    protected function parseUri($uri) {}
    /** @return string|null */
    public function getName() {}
    /** @return $this */
    public function name($name) {}
    /** @return bool */
    public function named(...$patterns) {}
    /** @return $this */
    public function uses($action) {}
    /** @return string */
    protected function addGroupNamespaceToStringUses($action) {}
    /** @return string */
    public function getActionName() {}
    /** @return string */
    public function getActionMethod() {}
    /** @return mixed */
    public function getAction($key = NULL) {}
    /** @return $this */
    public function setAction(array $action) {}
    /** @return \Closure|null */
    public function getMissing() {}
    /** @return $this */
    public function missing($missing) {}
    /** @return array */
    public function gatherMiddleware() {}
    /** @return ($middleware */
    public function middleware($middleware = NULL) {}
    /** @return $this */
    public function can($ability, $models = array (
)) {}
    /** @return array */
    public function controllerMiddleware() {}
    /** @return array */
    protected function staticallyProvidedControllerMiddleware(string $class, string $method) {}
    /** @return $this */
    public function withoutMiddleware($middleware) {}
    /** @return array */
    public function excludedMiddleware() {}
    /** @return $this */
    public function scopeBindings() {}
    /** @return $this */
    public function withoutScopedBindings() {}
    /** @return bool */
    public function enforcesScopedBindings() {}
    /** @return bool */
    public function preventsScopedBindings() {}
    /** @return $this */
    public function block($lockSeconds = 10, $waitSeconds = 10) {}
    /** @return $this */
    public function withoutBlocking() {}
    /** @return int|null */
    public function locksFor() {}
    /** @return int|null */
    public function waitsFor() {}
    /** @return \Illuminate\Routing\Contracts\ControllerDispatcher */
    public function controllerDispatcher() {}
    /** @return array */
    public static function getValidators() {}
    /** @return \Symfony\Component\Routing\Route */
    public function toSymfonyRoute() {}
    /** @return array<string, */
    public function getOptionalParameterNames() {}
    /** @return \Symfony\Component\Routing\CompiledRoute */
    public function getCompiled() {}
    /** @return $this */
    public function setRouter(\Illuminate\Routing\Router $router) {}
    /** @return $this */
    public function setContainer(\Illuminate\Container\Container $container) {}
    /** @return void */
    public function prepareForSerialization() {}
    /** @return mixed */
    public function __get($key) {}
}
