<?php

namespace Illuminate\Support\Traits;

trait Macroable
{
    protected static $macros = array (
);
    public static function macro($name, $macro) {}
    public static function mixin($mixin, $replace = true) {}
    public static function hasMacro($name) {}
    public static function flushMacros() {}
    public static function __callStatic($method, $parameters) {}
    public function __call($method, $parameters) {}
}
