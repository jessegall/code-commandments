<?php

namespace Illuminate\Http\Resources;

trait DelegatesToResource
{
    use \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Support\Traits\Macroable;
    /** @return mixed */
    public function getRouteKey() {}
    /** @return string */
    public function getRouteKeyName() {}
    /** @return void */
    public function resolveRouteBinding($value, $field = NULL) {}
    /** @return void */
    public function resolveChildRouteBinding($childType, $value, $field = NULL) {}
    public function offsetExists($offset): bool {}
    public function offsetGet($offset): mixed {}
    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
    /** @return bool */
    public function __isset($key) {}
    /** @return void */
    public function __unset($key) {}
    /** @return mixed */
    public function __get($key) {}
    /** @return mixed */
    public function __call($method, $parameters) {}
    /** @return mixed */
    protected function forwardCallTo($object, $method, $parameters) {}
    /** @return mixed */
    protected function forwardDecoratedCallTo($object, $method, $parameters) {}
    /** @return never */
    protected static function throwBadMethodCallException($method) {}
    /** @return void */
    public static function macro($name, $macro) {}
    /** @return void */
    public static function mixin($mixin, $replace = true) {}
    /** @return bool */
    public static function hasMacro($name) {}
    /** @return void */
    public static function flushMacros() {}
    /** @return mixed */
    public static function __callStatic($method, $parameters) {}
    /** @return mixed */
    public function macroCall($method, $parameters) {}
}
