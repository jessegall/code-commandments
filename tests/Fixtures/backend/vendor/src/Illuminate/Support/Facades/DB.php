<?php

namespace Illuminate\Support\Facades;

class DB extends \Illuminate\Support\Facades\Facade
{
    public static function prohibitDestructiveCommands(bool $prohibit = true) {}
    protected static function getFacadeAccessor() {}
}
