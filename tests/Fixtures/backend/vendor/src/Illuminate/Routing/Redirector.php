<?php

namespace Illuminate\Routing;

class Redirector
{
    use \Illuminate\Support\Traits\Macroable;
    protected $generator = NULL;
    protected $session = NULL;
    public function __construct(\Illuminate\Routing\UrlGenerator $generator) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function back($status = 302, $headers = array (
), $fallback = false) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function refresh($status = 302, $headers = array (
)) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function guest($path, $status = 302, $headers = array (
), $secure = NULL) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function intended($default = '/', $status = 302, $headers = array (
), $secure = NULL) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function to($path, $status = 302, $headers = array (
), $secure = NULL) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function away($path, $status = 302, $headers = array (
)) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function secure($path, $status = 302, $headers = array (
)) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function route($route, $parameters = array (
), $status = 302, $headers = array (
)) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function signedRoute($route, $parameters = array (
), $expiration = NULL, $status = 302, $headers = array (
)) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function temporarySignedRoute($route, $expiration, $parameters = array (
), $status = 302, $headers = array (
)) {}
    /** @return \Illuminate\Http\RedirectResponse */
    public function action($action, $parameters = array (
), $status = 302, $headers = array (
)) {}
    /** @return \Illuminate\Http\RedirectResponse */
    protected function createRedirect($path, $status, $headers) {}
    /** @return \Illuminate\Routing\UrlGenerator */
    public function getUrlGenerator() {}
    /** @return void */
    public function setSession(\Illuminate\Session\Store $session) {}
    /** @return string|null */
    public function getIntendedUrl() {}
    /** @return $this */
    public function setIntendedUrl($url) {}
}
