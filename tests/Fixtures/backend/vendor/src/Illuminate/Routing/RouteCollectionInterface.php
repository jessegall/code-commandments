<?php

namespace Illuminate\Routing;

interface RouteCollectionInterface
{
    public function add(\Illuminate\Routing\Route $route);
    public function refreshNameLookups();
    public function refreshActionLookups();
    public function match(\Illuminate\Http\Request $request);
    public function get($method = NULL);
    public function hasNamedRoute($name);
    public function getByName($name);
    public function getByAction($action);
    public function getRoutes();
    public function getRoutesByMethod();
    public function getRoutesByName();
}
