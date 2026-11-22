<?php

namespace Illuminate\Routing;

abstract class Controller
{
    protected $middleware = array (
);
    public function middleware($middleware, array $options = array (
)) {}
    public function getMiddleware() {}
    public function callAction($method, $parameters) {}
    public function __call($method, $parameters) {}
}
