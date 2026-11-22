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
    public static function capture() {}
    public function instance() {}
    public function method() {}
    public function uri() {}
    public function root() {}
    public function url() {}
    public function fullUrl() {}
    public function fullUrlWithQuery(array $query) {}
    public function fullUrlWithoutQuery($keys) {}
    public function path() {}
    public function decodedPath() {}
    public function segment($index, $default = NULL) {}
    public function segments() {}
    public function is(...$patterns) {}
    public function routeIs(...$patterns) {}
    public function fullUrlIs(...$patterns) {}
    public function host() {}
    public function httpHost() {}
    public function schemeAndHttpHost() {}
    public function ajax() {}
    public function pjax() {}
    public function prefetch() {}
    public function secure() {}
    public function ip() {}
    public function ips() {}
    public function userAgent() {}
    public function getAcceptableContentTypes(): array {}
    public function merge(array $input) {}
    public function mergeIfMissing(array $input) {}
    public function replace(array $input) {}
    public function get(string $key, mixed $default = NULL): mixed {}
    public function json($key = NULL, $default = NULL) {}
    protected function getInputSource() {}
    public static function createFrom(\Illuminate\Http\Request $from, $to = NULL) {}
    public static function createFromBase(\Symfony\Component\HttpFoundation\Request $request) {}
    public function duplicate(?array $query = NULL, ?array $request = NULL, ?array $attributes = NULL, ?array $cookies = NULL, ?array $files = NULL, ?array $server = NULL): static {}
    protected function filterFiles($files) {}
    public function hasSession(bool $skipIfUninitialized = false): bool {}
    public function getSession(): \Symfony\Component\HttpFoundation\Session\SessionInterface {}
    public function session() {}
    public function setLaravelSession($session) {}
    public function setRequestLocale(string $locale) {}
    public function setDefaultRequestLocale(string $locale) {}
    public function user($guard = NULL) {}
    public function route($param = NULL, $default = NULL) {}
    public function fingerprint() {}
    public function setJson($json) {}
    public function getUserResolver() {}
    public function setUserResolver(\Closure $callback) {}
    public function getRouteResolver() {}
    public function setRouteResolver(\Closure $callback) {}
    public function toArray(): array {}
    public function offsetExists($offset): bool {}
    public function offsetGet($offset): mixed {}
    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
    public function __isset($key) {}
    public function __get($key) {}
}
