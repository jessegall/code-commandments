<?php

namespace Illuminate\Contracts\Routing;

interface UrlGenerator
{
    public function current();
    public function previous($fallback = false);
    public function to($path, $extra = array (
), $secure = NULL);
    public function secure($path, $parameters = array (
));
    public function asset($path, $secure = NULL);
    public function route($name, $parameters = array (
), $absolute = true);
    public function signedRoute($name, $parameters = array (
), $expiration = NULL, $absolute = true);
    public function temporarySignedRoute($name, $expiration, $parameters = array (
), $absolute = true);
    public function query($path, $query = array (
), $extra = array (
), $secure = NULL);
    public function action($action, $parameters = array (
), $absolute = true);
    public function getRootControllerNamespace();
    public function setRootControllerNamespace($rootNamespace);
}
