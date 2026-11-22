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
    /** @return \Illuminate\Contracts\Container\ContextualBindingBuilder */
    public function when($concrete) {}
    /** @return void */
    public function whenHasAttribute(string $attribute, \Closure $handler) {}
    /** @return bool */
    public function bound($abstract) {}
    public function has(string $id): bool {}
    /** @return bool */
    public function resolved($abstract) {}
    /** @return bool */
    public function isShared($abstract) {}
    protected function getScopedTyped(\ReflectionClass|string $reflection): ?string {}
    /** @return bool */
    public function isAlias($name) {}
    /** @return void */
    public function bind($abstract, $concrete = NULL, $shared = false) {}
    /** @return \Closure */
    protected function getClosure($abstract, $concrete) {}
    /** @return bool */
    public function hasMethodBinding($method) {}
    /** @return void */
    public function bindMethod($method, $callback) {}
    /** @return string */
    protected function parseBindMethod($method) {}
    /** @return mixed */
    public function callMethodBinding($method, $instance) {}
    /** @return void */
    public function addContextualBinding($concrete, $abstract, $implementation) {}
    /** @return void */
    public function bindIf($abstract, $concrete = NULL, $shared = false) {}
    /** @return void */
    public function singleton($abstract, $concrete = NULL) {}
    /** @return void */
    public function singletonIf($abstract, $concrete = NULL) {}
    /** @return void */
    public function scoped($abstract, $concrete = NULL) {}
    /** @return void */
    public function scopedIf($abstract, $concrete = NULL) {}
    /** @return void */
    protected function bindBasedOnClosureReturnTypes($abstract, $concrete = NULL, $shared = false) {}
    /** @return void */
    public function extend($abstract, \Closure $closure) {}
    /** @return TInstance */
    public function instance($abstract, $instance) {}
    /** @return void */
    protected function removeAbstractAlias($searched) {}
    /** @return void */
    public function tag($abstracts, $tags) {}
    /** @return iterable */
    public function tagged($tag) {}
    /** @return void */
    public function alias($abstract, $alias) {}
    /** @return mixed */
    public function rebinding($abstract, \Closure $callback) {}
    /** @return mixed */
    public function refresh($abstract, $target, $method) {}
    /** @return void */
    protected function rebound($abstract) {}
    /** @return array */
    protected function getReboundCallbacks($abstract) {}
    /** @return \Closure */
    public function wrap(\Closure $callback, array $parameters = array (
)) {}
    /** @return mixed */
    public function call($callback, array $parameters = array (
), $defaultMethod = NULL) {}
    /** @return string|false */
    protected function getClassForCallable($callback) {}
    /** @return ($abstract */
    public function factory($abstract) {}
    /** @return ($abstract */
    public function makeWith($abstract, array $parameters = array (
)) {}
    /** @return ($abstract */
    public function make($abstract, array $parameters = array (
)) {}
    /** @return ($id */
    public function get(string $id) {}
    /** @return ($abstract */
    protected function resolve($abstract, $parameters = array (
), $raiseEvents = true) {}
    /** @return mixed */
    protected function getConcrete($abstract) {}
    /** @return mixed */
    protected function getConcreteBindingFromAttributes($abstract) {}
    /** @return \Closure|string|array|null */
    protected function getContextualConcrete($abstract) {}
    /** @return \Closure|string|null */
    protected function findInContextualBindings($abstract) {}
    /** @return bool */
    protected function isBuildable($concrete, $abstract) {}
    /** @return TClass */
    public function build($concrete) {}
    /** @return TClass */
    protected function buildSelfBuildingInstance($concrete, $reflector) {}
    /** @return array */
    protected function resolveDependencies(array $dependencies) {}
    /** @return bool */
    protected function hasParameterOverride($dependency) {}
    /** @return mixed */
    protected function getParameterOverride($dependency) {}
    /** @return array */
    protected function getLastParameterOverride() {}
    /** @return mixed */
    protected function resolvePrimitive(\ReflectionParameter $parameter) {}
    /** @return mixed */
    protected function resolveClass(\ReflectionParameter $parameter, ?string $className = NULL) {}
    /** @return mixed */
    protected function resolveVariadicClass(\ReflectionParameter $parameter) {}
    /** @return mixed */
    public function resolveFromAttribute(\ReflectionAttribute $attribute) {}
    /** @return void */
    protected function notInstantiable($concrete) {}
    /** @return void */
    protected function unresolvablePrimitive(\ReflectionParameter $parameter) {}
    /** @return void */
    public function beforeResolving($abstract, ?\Closure $callback = NULL) {}
    /** @return void */
    public function resolving($abstract, ?\Closure $callback = NULL) {}
    /** @return void */
    public function afterResolving($abstract, ?\Closure $callback = NULL) {}
    /** @return void */
    public function afterResolvingAttribute(string $attribute, \Closure $callback) {}
    /** @return void */
    protected function fireBeforeResolvingCallbacks($abstract, $parameters = array (
)) {}
    /** @return void */
    protected function fireBeforeCallbackArray($abstract, $parameters, array $callbacks) {}
    /** @return void */
    protected function fireResolvingCallbacks($abstract, $object) {}
    /** @return void */
    protected function fireAfterResolvingCallbacks($abstract, $object) {}
    /** @return void */
    public function fireAfterResolvingAttributeCallbacks(array $attributes, $object) {}
    /** @return array */
    protected function getCallbacksForType($abstract, $object, array $callbacksPerType) {}
    /** @return void */
    protected function fireCallbackArray($object, array $callbacks) {}
    /** @return class-string|string|null */
    public function currentlyResolving() {}
    /** @return array */
    public function getBindings() {}
    /** @return string */
    public function getAlias($abstract) {}
    /** @return array */
    protected function getExtenders($abstract) {}
    /** @return void */
    public function forgetExtenders($abstract) {}
    /** @return void */
    protected function dropStaleInstances($abstract) {}
    /** @return void */
    public function forgetInstance($abstract) {}
    /** @return void */
    public function forgetInstances() {}
    /** @return void */
    public function forgetScopedInstances() {}
    /** @return void */
    public function resolveEnvironmentUsing(?callable $callback) {}
    /** @return bool */
    public function currentEnvironmentIs($environments) {}
    /** @return void */
    public function flush() {}
    /** @return static */
    public static function getInstance() {}
    /** @return \Illuminate\Contracts\Container\Container|static */
    public static function setInstance(?\Illuminate\Contracts\Container\Container $container = NULL) {}
    public function offsetExists($key): bool {}
    public function offsetGet($key): mixed {}
    public function offsetSet($key, $value): void {}
    public function offsetUnset($key): void {}
    /** @return mixed */
    public function __get($key) {}
    /** @return void */
    public function __set($key, $value) {}
}
