<?php

namespace Illuminate\Support\Facades;

class Date extends \Illuminate\Support\Facades\Facade
{
    public const DEFAULT_FACADE = 'Illuminate\\Support\\DateFactory';
    /** @return string */
    protected static function getFacadeAccessor() {}
    /** @return mixed */
    protected static function resolveFacadeInstance($name) {}
}
