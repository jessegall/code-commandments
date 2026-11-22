<?php

namespace Illuminate\Support\Facades;

class Date extends \Illuminate\Support\Facades\Facade
{
    public const DEFAULT_FACADE = 'Illuminate\\Support\\DateFactory';
    protected static function getFacadeAccessor() {}
    protected static function resolveFacadeInstance($name) {}
}
