<?php

namespace Illuminate\Contracts\Routing;

interface UrlGenerator
{
    /** @return string */
    public function current();
    /** @return string */
    public function previous($fallback = false);
    /** @return string */
    public function to($path, $extra = array (
), $secure = NULL);
    /** @return string */
    public function secure($path, $parameters = array (
));
    /** @return string */
    public function asset($path, $secure = NULL);
    /** @return string */
    public function route($name, $parameters = array (
), $absolute = true);
    /** @return string */
    public function signedRoute($name, $parameters = array (
), $expiration = NULL, $absolute = true);
    /** @return string */
    public function temporarySignedRoute($name, $expiration, $parameters = array (
), $absolute = true);
    /** @return string */
    public function query($path, $query = array (
), $extra = array (
), $secure = NULL);
    /** @return string */
    public function action($action, $parameters = array (
), $absolute = true);
    /** @return string */
    public function getRootControllerNamespace();
    /** @return $this */
    public function setRootControllerNamespace($rootNamespace);
}
