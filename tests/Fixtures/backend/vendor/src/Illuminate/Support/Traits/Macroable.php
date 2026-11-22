<?php

namespace Illuminate\Support\Traits;

trait Macroable
{
    protected static $macros = array (
);
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
    public function __call($method, $parameters) {}
}
