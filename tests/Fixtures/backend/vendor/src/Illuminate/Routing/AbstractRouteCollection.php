<?php

namespace Illuminate\Routing;

abstract class AbstractRouteCollection implements \Countable, \IteratorAggregate, \Illuminate\Routing\RouteCollectionInterface, \Traversable
{
    protected function handleMatchedRoute(\Illuminate\Http\Request $request, $route) {}
    protected function checkForAlternateVerbs($request) {}
    protected function matchAgainstRoutes(array $routes, $request, $includingMethod = true) {}
    protected function getRouteForMethods($request, array $methods) {}
    protected function requestMethodNotAllowed($request, array $others, $method) {}
    protected function methodNotAllowed(array $others, $method) {}
    public function compile() {}
    public function dumper() {}
    public function toSymfonyRouteCollection() {}
    protected function addToSymfonyRoutesCollection(\Symfony\Component\Routing\RouteCollection $symfonyRoutes, \Illuminate\Routing\Route $route) {}
    protected function generateRouteName() {}
    public function getIterator(): \Traversable {}
    public function count(): int {}
}
