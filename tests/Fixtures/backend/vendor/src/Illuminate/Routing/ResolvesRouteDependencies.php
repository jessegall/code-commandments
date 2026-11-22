<?php

namespace Illuminate\Routing;

trait ResolvesRouteDependencies
{
    protected function resolveClassMethodDependencies(array $parameters, $instance, $method) {}
    public function resolveMethodDependencies(array $parameters, \ReflectionFunctionAbstract $reflector) {}
    protected function transformDependency(\ReflectionParameter $parameter, $parameters, $skippableValue) {}
    protected function alreadyInParameters($class, array $parameters) {}
    protected function spliceIntoParameters(array &$parameters, $offset, $value) {}
}
