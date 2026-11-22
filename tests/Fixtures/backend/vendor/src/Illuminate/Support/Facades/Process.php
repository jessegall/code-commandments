<?php

namespace Illuminate\Support\Facades;

class Process extends \Illuminate\Support\Facades\Facade
{
    /** @return string */
    protected static function getFacadeAccessor() {}
    /** @return \Illuminate\Process\Factory */
    public static function fake(\Closure|array|null $callback = NULL) {}
}
