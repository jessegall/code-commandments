<?php

namespace Illuminate\Support\Facades;

abstract class Facade
{
    protected static $app = NULL;
    protected static $resolvedInstance = NULL;
    protected static $cached = true;
    /** @return void */
    public static function resolved(\Closure $callback) {}
    /** @return \Mockery\MockInterface */
    public static function spy() {}
    /** @return \Mockery\MockInterface */
    public static function partialMock() {}
    /** @return \Mockery\Expectation */
    public static function shouldReceive() {}
    /** @return \Mockery\Expectation */
    public static function expects() {}
    /** @return \Mockery\MockInterface */
    protected static function createFreshMockInstance() {}
    /** @return \Mockery\MockInterface */
    protected static function createMock() {}
    /** @return bool */
    protected static function isMock() {}
    /** @return string|null */
    protected static function getMockableClass() {}
    /** @return void */
    public static function swap($instance) {}
    /** @return bool */
    public static function isFake() {}
    /** @return mixed */
    public static function getFacadeRoot() {}
    /** @return string */
    protected static function getFacadeAccessor() {}
    /** @return mixed */
    protected static function resolveFacadeInstance($name) {}
    /** @return void */
    public static function clearResolvedInstance($name = NULL) {}
    /** @return void */
    public static function clearResolvedInstances() {}
    /** @return \Illuminate\Support\Collection */
    public static function defaultAliases() {}
    /** @return \Illuminate\Contracts\Foundation\Application|null */
    public static function getFacadeApplication() {}
    /** @return void */
    public static function setFacadeApplication($app) {}
    /** @return mixed */
    public static function __callStatic($method, $args) {}
}
