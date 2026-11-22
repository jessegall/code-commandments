<?php

namespace Illuminate\Http;

trait ResponseTrait
{
    public $original = NULL;
    public $exception = NULL;
    /** @return int */
    public function status() {}
    /** @return string */
    public function statusText() {}
    /** @return string */
    public function content() {}
    /** @return mixed */
    public function getOriginalContent() {}
    /** @return $this */
    public function header($key, $values, $replace = true) {}
    /** @return $this */
    public function withHeaders($headers) {}
    /** @return $this */
    public function withoutHeader($key) {}
    /** @return $this */
    public function cookie($cookie) {}
    /** @return $this */
    public function withCookie($cookie) {}
    /** @return $this */
    public function withoutCookie($cookie, $path = NULL, $domain = NULL) {}
    /** @return string|null */
    public function getCallback() {}
    /** @return $this */
    public function withException(\Throwable $e) {}
    /** @return never */
    public function throwResponse() {}
}
