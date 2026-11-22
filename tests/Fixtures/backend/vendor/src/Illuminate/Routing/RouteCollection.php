<?php

namespace Illuminate\Routing;

class RouteCollection extends \Illuminate\Routing\AbstractRouteCollection
{
    protected $routes = array (
);
    protected $allRoutes = array (
);
    protected $nameList = array (
);
    protected $actionList = array (
);
    public function add(\Illuminate\Routing\Route $route) {}
    protected function addToCollections($route) {}
    protected function addLookups($route) {}
    protected function addToActionList($action, $route) {}
    protected function inActionLookup($controller) {}
    protected function inNameLookup($name) {}
    public function refreshNameLookups() {}
    public function refreshActionLookups() {}
    public function match(\Illuminate\Http\Request $request) {}
    public function get($method = NULL) {}
    public function hasNamedRoute($name) {}
    public function getByName($name) {}
    public function getByAction($action) {}
    public function getRoutes() {}
    public function getRoutesByMethod() {}
    public function getRoutesByName() {}
    public function toSymfonyRouteCollection() {}
    public function toCompiledRouteCollection(\Illuminate\Routing\Router $router, \Illuminate\Container\Container $container) {}
}
