<?php

namespace Illuminate\Http;

class Request extends \Symfony\Component\HttpFoundation\Request implements \Illuminate\Contracts\Support\Arrayable, \ArrayAccess
{
    use \Illuminate\Http\Concerns\CanBePrecognitive, \Illuminate\Http\Concerns\InteractsWithContentTypes, \Illuminate\Http\Concerns\InteractsWithFlashData, \Illuminate\Http\Concerns\InteractsWithInput, \Illuminate\Support\Traits\Conditionable, \Illuminate\Support\Traits\Macroable;
    protected $json = NULL;
    protected $convertedFiles = NULL;
    protected $userResolver = NULL;
    protected $routeResolver = NULL;
    protected $cachedAcceptHeader = NULL;
    /** @return static */
    public static function capture() {}
    /** @return $this */
    public function instance() {}
    /** @return string */
    public function method() {}
    /** @return \Illuminate\Support\Uri */
    public function uri() {}
    /** @return string */
    public function root() {}
    /** @return string */
    public function url() {}
    /** @return string */
    public function fullUrl() {}
    /** @return string */
    public function fullUrlWithQuery(array $query) {}
    /** @return string */
    public function fullUrlWithoutQuery($keys) {}
    /** @return string */
    public function path() {}
    /** @return string */
    public function decodedPath() {}
    /** @return string|null */
    public function segment($index, $default = NULL) {}
    /** @return array */
    public function segments() {}
    /** @return bool */
    public function is(...$patterns) {}
    /** @return bool */
    public function routeIs(...$patterns) {}
    /** @return bool */
    public function fullUrlIs(...$patterns) {}
    /** @return string */
    public function host() {}
    /** @return string */
    public function httpHost() {}
    /** @return string */
    public function schemeAndHttpHost() {}
    /** @return bool */
    public function ajax() {}
    /** @return bool */
    public function pjax() {}
    /** @return bool */
    public function prefetch() {}
    /** @return bool */
    public function secure() {}
    /** @return string|null */
    public function ip() {}
    /** @return array */
    public function ips() {}
    /** @return string|null */
    public function userAgent() {}
    public function getAcceptableContentTypes(): array {}
    /** @return $this */
    public function merge(array $input) {}
    /** @return $this */
    public function mergeIfMissing(array $input) {}
    /** @return $this */
    public function replace(array $input) {}
    public function get(string $key, mixed $default = NULL): mixed {}
    /** @return ($key */
    public function json($key = NULL, $default = NULL) {}
    /** @return \Symfony\Component\HttpFoundation\InputBag */
    protected function getInputSource() {}
    /** @return static */
    public static function createFrom(\Illuminate\Http\Request $from, $to = NULL) {}
    /** @return static */
    public static function createFromBase(\Symfony\Component\HttpFoundation\Request $request) {}
    public function duplicate(?array $query = NULL, ?array $request = NULL, ?array $attributes = NULL, ?array $cookies = NULL, ?array $files = NULL, ?array $server = NULL): static {}
    /** @return mixed */
    protected function filterFiles($files) {}
    public function hasSession(bool $skipIfUninitialized = false): bool {}
    public function getSession(): \Symfony\Component\HttpFoundation\Session\SessionInterface {}
    /** @return \Illuminate\Contracts\Session\Session */
    public function session() {}
    /** @return void */
    public function setLaravelSession($session) {}
    /** @return void */
    public function setRequestLocale(string $locale) {}
    /** @return void */
    public function setDefaultRequestLocale(string $locale) {}
    /** @return mixed */
    public function user($guard = NULL) {}
    /** @return ($param */
    public function route($param = NULL, $default = NULL) {}
    /** @return string */
    public function fingerprint() {}
    /** @return $this */
    public function setJson($json) {}
    /** @return \Closure */
    public function getUserResolver() {}
    /** @return $this */
    public function setUserResolver(\Closure $callback) {}
    /** @return \Closure */
    public function getRouteResolver() {}
    /** @return $this */
    public function setRouteResolver(\Closure $callback) {}
    public function toArray(): array {}
    public function offsetExists($offset): bool {}
    public function offsetGet($offset): mixed {}
    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
    /** @return bool */
    public function __isset($key) {}
    /** @return mixed */
    public function __get($key) {}
}
