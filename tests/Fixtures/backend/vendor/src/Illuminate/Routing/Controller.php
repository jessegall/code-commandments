<?php

namespace Illuminate\Routing;

abstract class Controller
{
    protected $middleware = array (
);
    /** @return \Illuminate\Routing\ControllerMiddlewareOptions */
    public function middleware($middleware, array $options = array (
)) {}
    /** @return array */
    public function getMiddleware() {}
    /** @return \Symfony\Component\HttpFoundation\Response */
    public function callAction($method, $parameters) {}
    /** @return mixed */
    public function __call($method, $parameters) {}
}
