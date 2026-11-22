<?php

namespace Illuminate\Support\Facades;

class DB extends \Illuminate\Support\Facades\Facade
{
    /** @return void */
    public static function prohibitDestructiveCommands(bool $prohibit = true) {}
    /** @return string */
    protected static function getFacadeAccessor() {}
}
