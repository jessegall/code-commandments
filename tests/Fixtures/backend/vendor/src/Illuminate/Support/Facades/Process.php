<?php

namespace Illuminate\Support\Facades;

class Process extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor() {}
    public static function fake(\Closure|array|null $callback = NULL) {}
}
