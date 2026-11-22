<?php

namespace Illuminate\Support\Facades;

abstract class Facade
{
    protected static $app = NULL;
    protected static $resolvedInstance = NULL;
    protected static $cached = true;
    public static function resolved(\Closure $callback) {}
    public static function spy() {}
    public static function partialMock() {}
    public static function shouldReceive() {}
    public static function expects() {}
    protected static function createFreshMockInstance() {}
    protected static function createMock() {}
    protected static function isMock() {}
    protected static function getMockableClass() {}
    public static function swap($instance) {}
    public static function isFake() {}
    public static function getFacadeRoot() {}
    protected static function getFacadeAccessor() {}
    protected static function resolveFacadeInstance($name) {}
    public static function clearResolvedInstance($name = NULL) {}
    public static function clearResolvedInstances() {}
    public static function defaultAliases() {}
    public static function getFacadeApplication() {}
    public static function setFacadeApplication($app) {}
    public static function __callStatic($method, $args) {}
}
