<?php

namespace Illuminate\Support\Facades;

class Cookie extends \Illuminate\Support\Facades\Facade
{
    /** @return bool */
    public static function has($key) {}
    /** @return string|array|null */
    public static function get($key = NULL, $default = NULL) {}
    /** @return string */
    protected static function getFacadeAccessor() {}
}
