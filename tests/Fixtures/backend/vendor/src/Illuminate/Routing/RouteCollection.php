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
    /** @return \Illuminate\Routing\Route */
    public function add(\Illuminate\Routing\Route $route) {}
    /** @return void */
    protected function addToCollections($route) {}
    /** @return void */
    protected function addLookups($route) {}
    /** @return void */
    protected function addToActionList($action, $route) {}
    /** @return bool */
    protected function inActionLookup($controller) {}
    /** @return bool */
    protected function inNameLookup($name) {}
    /** @return void */
    public function refreshNameLookups() {}
    /** @return void */
    public function refreshActionLookups() {}
    /** @return \Illuminate\Routing\Route */
    public function match(\Illuminate\Http\Request $request) {}
    /** @return \Illuminate\Routing\Route[] */
    public function get($method = NULL) {}
    /** @return bool */
    public function hasNamedRoute($name) {}
    /** @return \Illuminate\Routing\Route|null */
    public function getByName($name) {}
    /** @return \Illuminate\Routing\Route|null */
    public function getByAction($action) {}
    /** @return \Illuminate\Routing\Route[] */
    public function getRoutes() {}
    /** @return array */
    public function getRoutesByMethod() {}
    /** @return \Illuminate\Routing\Route[] */
    public function getRoutesByName() {}
    /** @return \Symfony\Component\Routing\RouteCollection */
    public function toSymfonyRouteCollection() {}
    /** @return \Illuminate\Routing\CompiledRouteCollection */
    public function toCompiledRouteCollection(\Illuminate\Routing\Router $router, \Illuminate\Container\Container $container) {}
}
