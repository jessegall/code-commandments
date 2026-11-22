<?php

namespace Illuminate\Support\Facades;

class Schema extends \Illuminate\Support\Facades\Facade
{
    protected static $cached = false;
    public static function connection($name) {}
    protected static function getFacadeAccessor() {}
}
