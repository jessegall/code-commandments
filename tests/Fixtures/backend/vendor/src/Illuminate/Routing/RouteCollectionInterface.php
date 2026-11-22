<?php

namespace Illuminate\Routing;

interface RouteCollectionInterface
{
    /** @return \Illuminate\Routing\Route */
    public function add(\Illuminate\Routing\Route $route);
    /** @return void */
    public function refreshNameLookups();
    /** @return void */
    public function refreshActionLookups();
    /** @return \Illuminate\Routing\Route */
    public function match(\Illuminate\Http\Request $request);
    /** @return \Illuminate\Routing\Route[] */
    public function get($method = NULL);
    /** @return bool */
    public function hasNamedRoute($name);
    /** @return \Illuminate\Routing\Route|null */
    public function getByName($name);
    /** @return \Illuminate\Routing\Route|null */
    public function getByAction($action);
    /** @return \Illuminate\Routing\Route[] */
    public function getRoutes();
    /** @return array */
    public function getRoutesByMethod();
    /** @return \Illuminate\Routing\Route[] */
    public function getRoutesByName();
}
