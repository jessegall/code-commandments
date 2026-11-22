<?php

namespace Illuminate\Http;

trait ResponseTrait
{
    public $original = NULL;
    public $exception = NULL;
    public function status() {}
    public function statusText() {}
    public function content() {}
    public function getOriginalContent() {}
    public function header($key, $values, $replace = true) {}
    public function withHeaders($headers) {}
    public function withoutHeader($key) {}
    public function cookie($cookie) {}
    public function withCookie($cookie) {}
    public function withoutCookie($cookie, $path = NULL, $domain = NULL) {}
    public function getCallback() {}
    public function withException(\Throwable $e) {}
    public function throwResponse() {}
}
