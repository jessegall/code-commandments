<?php

namespace Illuminate\Http\Resources;

trait DelegatesToResource
{
    use \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Support\Traits\Macroable;
    public function getRouteKey() {}
    public function getRouteKeyName() {}
    public function resolveRouteBinding($value, $field = NULL) {}
    public function resolveChildRouteBinding($childType, $value, $field = NULL) {}
    public function offsetExists($offset): bool {}
    public function offsetGet($offset): mixed {}
    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
    public function __isset($key) {}
    public function __unset($key) {}
    public function __get($key) {}
    public function __call($method, $parameters) {}
    protected function forwardCallTo($object, $method, $parameters) {}
    protected function forwardDecoratedCallTo($object, $method, $parameters) {}
    protected static function throwBadMethodCallException($method) {}
    public static function macro($name, $macro) {}
    public static function mixin($mixin, $replace = true) {}
    public static function hasMacro($name) {}
    public static function flushMacros() {}
    public static function __callStatic($method, $parameters) {}
    public function macroCall($method, $parameters) {}
}
