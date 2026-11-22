<?php

namespace Illuminate\Routing;

class Redirector
{
    use \Illuminate\Support\Traits\Macroable;
    protected $generator = NULL;
    protected $session = NULL;
    public function __construct(\Illuminate\Routing\UrlGenerator $generator) {}
    public function back($status = 302, $headers = array (
), $fallback = false) {}
    public function refresh($status = 302, $headers = array (
)) {}
    public function guest($path, $status = 302, $headers = array (
), $secure = NULL) {}
    public function intended($default = '/', $status = 302, $headers = array (
), $secure = NULL) {}
    public function to($path, $status = 302, $headers = array (
), $secure = NULL) {}
    public function away($path, $status = 302, $headers = array (
)) {}
    public function secure($path, $status = 302, $headers = array (
)) {}
    public function route($route, $parameters = array (
), $status = 302, $headers = array (
)) {}
    public function signedRoute($route, $parameters = array (
), $expiration = NULL, $status = 302, $headers = array (
)) {}
    public function temporarySignedRoute($route, $expiration, $parameters = array (
), $status = 302, $headers = array (
)) {}
    public function action($action, $parameters = array (
), $status = 302, $headers = array (
)) {}
    protected function createRedirect($path, $status, $headers) {}
    public function getUrlGenerator() {}
    public function setSession(\Illuminate\Session\Store $session) {}
    public function getIntendedUrl() {}
    public function setIntendedUrl($url) {}
}
