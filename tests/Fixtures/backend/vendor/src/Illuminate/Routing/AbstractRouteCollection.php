<?php

namespace Illuminate\Routing;

abstract class AbstractRouteCollection implements \Countable, \IteratorAggregate, \Illuminate\Routing\RouteCollectionInterface, \Traversable
{
    /** @return \Illuminate\Routing\Route */
    protected function handleMatchedRoute(\Illuminate\Http\Request $request, $route) {}
    /** @return array */
    protected function checkForAlternateVerbs($request) {}
    /** @return \Illuminate\Routing\Route|null */
    protected function matchAgainstRoutes(array $routes, $request, $includingMethod = true) {}
    /** @return \Illuminate\Routing\Route */
    protected function getRouteForMethods($request, array $methods) {}
    /** @return never */
    protected function requestMethodNotAllowed($request, array $others, $method) {}
    /** @return void */
    protected function methodNotAllowed(array $others, $method) {}
    /** @return array */
    public function compile() {}
    /** @return \Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper */
    public function dumper() {}
    /** @return \Symfony\Component\Routing\RouteCollection */
    public function toSymfonyRouteCollection() {}
    /** @return \Symfony\Component\Routing\RouteCollection */
    protected function addToSymfonyRoutesCollection(\Symfony\Component\Routing\RouteCollection $symfonyRoutes, \Illuminate\Routing\Route $route) {}
    /** @return string */
    protected function generateRouteName() {}
    public function getIterator(): \Traversable {}
    public function count(): int {}
}
