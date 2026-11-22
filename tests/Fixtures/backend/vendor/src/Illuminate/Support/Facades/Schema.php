<?php

namespace Illuminate\Support\Facades;

class Schema extends \Illuminate\Support\Facades\Facade
{
    protected static $cached = false;
    /** @return \Illuminate\Database\Schema\Builder */
    public static function connection($name) {}
    /** @return string */
    protected static function getFacadeAccessor() {}
}
