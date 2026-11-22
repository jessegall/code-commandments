<?php

namespace Illuminate\Contracts\Routing;

interface Registrar
{
    /** @return \Illuminate\Routing\Route */
    public function get($uri, $action);
    /** @return \Illuminate\Routing\Route */
    public function post($uri, $action);
    /** @return \Illuminate\Routing\Route */
    public function put($uri, $action);
    /** @return \Illuminate\Routing\Route */
    public function delete($uri, $action);
    /** @return \Illuminate\Routing\Route */
    public function patch($uri, $action);
    /** @return \Illuminate\Routing\Route */
    public function options($uri, $action);
    /** @return \Illuminate\Routing\Route */
    public function match($methods, $uri, $action);
    /** @return \Illuminate\Routing\PendingResourceRegistration */
    public function resource($name, $controller, array $options = array (
));
    /** @return void */
    public function group(array $attributes, $routes);
    /** @return \Illuminate\Routing\Route */
    public function substituteBindings($route);
    /** @return void */
    public function substituteImplicitBindings($route);
}
