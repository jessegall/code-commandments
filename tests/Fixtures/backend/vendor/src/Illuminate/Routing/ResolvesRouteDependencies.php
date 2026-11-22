<?php

namespace Illuminate\Routing;

trait ResolvesRouteDependencies
{
    /** @return array */
    protected function resolveClassMethodDependencies(array $parameters, $instance, $method) {}
    /** @return array */
    public function resolveMethodDependencies(array $parameters, \ReflectionFunctionAbstract $reflector) {}
    /** @return mixed */
    protected function transformDependency(\ReflectionParameter $parameter, $parameters, $skippableValue) {}
    /** @return bool */
    protected function alreadyInParameters($class, array $parameters) {}
    /** @return void */
    protected function spliceIntoParameters(array &$parameters, $offset, $value) {}
}
