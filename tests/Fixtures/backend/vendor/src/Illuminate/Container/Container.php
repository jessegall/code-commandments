<?php

namespace Illuminate\Container;

class Container implements \ArrayAccess, \Illuminate\Contracts\Container\Container, \Psr\Container\ContainerInterface
{
    use \Illuminate\Support\Traits\ReflectsClosures;
    protected static $instance = NULL;
    protected $resolved = array (
);
    protected $bindings = array (
);
    protected $methodBindings = array (
);
    protected $instances = array (
);
    protected $scopedInstances = array (
);
    protected $aliases = array (
);
    protected $abstractAliases = array (
);
    protected $extenders = array (
);
    protected $tags = array (
);
    protected $buildStack = array (
);
    protected $with = array (
);
    public $contextual = array (
);
    public $contextualAttributes = array (
);
    protected $checkedForAttributeBindings = array (
);
    protected $checkedForSingletonOrScopedAttributes = array (
);
    protected $reboundCallbacks = array (
);
    protected $globalBeforeResolvingCallbacks = array (
);
    protected $globalResolvingCallbacks = array (
);
    protected $globalAfterResolvingCallbacks = array (
);
    protected $beforeResolvingCallbacks = array (
);
    protected $resolvingCallbacks = array (
);
    protected $afterResolvingCallbacks = array (
);
    protected $afterResolvingAttributeCallbacks = array (
);
    protected $environmentResolver = NULL;
    public function when($concrete) {}
    public function whenHasAttribute(string $attribute, \Closure $handler) {}
    public function bound($abstract) {}
    public function has(string $id): bool {}
    public function resolved($abstract) {}
    public function isShared($abstract) {}
    protected function getScopedTyped(\ReflectionClass|string $reflection): ?string {}
    public function isAlias($name) {}
    public function bind($abstract, $concrete = NULL, $shared = false) {}
    protected function getClosure($abstract, $concrete) {}
    public function hasMethodBinding($method) {}
    public function bindMethod($method, $callback) {}
    protected function parseBindMethod($method) {}
    public function callMethodBinding($method, $instance) {}
    public function addContextualBinding($concrete, $abstract, $implementation) {}
    public function bindIf($abstract, $concrete = NULL, $shared = false) {}
    public function singleton($abstract, $concrete = NULL) {}
    public function singletonIf($abstract, $concrete = NULL) {}
    public function scoped($abstract, $concrete = NULL) {}
    public function scopedIf($abstract, $concrete = NULL) {}
    protected function bindBasedOnClosureReturnTypes($abstract, $concrete = NULL, $shared = false) {}
    public function extend($abstract, \Closure $closure) {}
    public function instance($abstract, $instance) {}
    protected function removeAbstractAlias($searched) {}
    public function tag($abstracts, $tags) {}
    public function tagged($tag) {}
    public function alias($abstract, $alias) {}
    public function rebinding($abstract, \Closure $callback) {}
    public function refresh($abstract, $target, $method) {}
    protected function rebound($abstract) {}
    protected function getReboundCallbacks($abstract) {}
    public function wrap(\Closure $callback, array $parameters = array (
)) {}
    public function call($callback, array $parameters = array (
), $defaultMethod = NULL) {}
    protected function getClassForCallable($callback) {}
    public function factory($abstract) {}
    public function makeWith($abstract, array $parameters = array (
)) {}
    public function make($abstract, array $parameters = array (
)) {}
    public function get(string $id) {}
    protected function resolve($abstract, $parameters = array (
), $raiseEvents = true) {}
    protected function getConcrete($abstract) {}
    protected function getConcreteBindingFromAttributes($abstract) {}
    protected function getContextualConcrete($abstract) {}
    protected function findInContextualBindings($abstract) {}
    protected function isBuildable($concrete, $abstract) {}
    public function build($concrete) {}
    protected function buildSelfBuildingInstance($concrete, $reflector) {}
    protected function resolveDependencies(array $dependencies) {}
    protected function hasParameterOverride($dependency) {}
    protected function getParameterOverride($dependency) {}
    protected function getLastParameterOverride() {}
    protected function resolvePrimitive(\ReflectionParameter $parameter) {}
    protected function resolveClass(\ReflectionParameter $parameter, ?string $className = NULL) {}
    protected function resolveVariadicClass(\ReflectionParameter $parameter) {}
    public function resolveFromAttribute(\ReflectionAttribute $attribute) {}
    protected function notInstantiable($concrete) {}
    protected function unresolvablePrimitive(\ReflectionParameter $parameter) {}
    public function beforeResolving($abstract, ?\Closure $callback = NULL) {}
    public function resolving($abstract, ?\Closure $callback = NULL) {}
    public function afterResolving($abstract, ?\Closure $callback = NULL) {}
    public function afterResolvingAttribute(string $attribute, \Closure $callback) {}
    protected function fireBeforeResolvingCallbacks($abstract, $parameters = array (
)) {}
    protected function fireBeforeCallbackArray($abstract, $parameters, array $callbacks) {}
    protected function fireResolvingCallbacks($abstract, $object) {}
    protected function fireAfterResolvingCallbacks($abstract, $object) {}
    public function fireAfterResolvingAttributeCallbacks(array $attributes, $object) {}
    protected function getCallbacksForType($abstract, $object, array $callbacksPerType) {}
    protected function fireCallbackArray($object, array $callbacks) {}
    public function currentlyResolving() {}
    public function getBindings() {}
    public function getAlias($abstract) {}
    protected function getExtenders($abstract) {}
    public function forgetExtenders($abstract) {}
    protected function dropStaleInstances($abstract) {}
    public function forgetInstance($abstract) {}
    public function forgetInstances() {}
    public function forgetScopedInstances() {}
    public function resolveEnvironmentUsing(?callable $callback) {}
    public function currentEnvironmentIs($environments) {}
    public function flush() {}
    public static function getInstance() {}
    public static function setInstance(?\Illuminate\Contracts\Container\Container $container = NULL) {}
    public function offsetExists($key): bool {}
    public function offsetGet($key): mixed {}
    public function offsetSet($key, $value): void {}
    public function offsetUnset($key): void {}
    public function __get($key) {}
    public function __set($key, $value) {}
}
