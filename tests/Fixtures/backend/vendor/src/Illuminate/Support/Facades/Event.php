<?php

namespace Illuminate\Support\Facades;

class Event extends \Illuminate\Support\Facades\Facade
{
    /** @return \Illuminate\Support\Testing\Fakes\EventFake */
    public static function fake($eventsToFake = array (
)) {}
    /** @return \Illuminate\Support\Testing\Fakes\EventFake */
    public static function fakeExcept($eventsToAllow) {}
    /** @return mixed */
    public static function fakeFor(callable $callable, array $eventsToFake = array (
)) {}
    /** @return mixed */
    public static function fakeExceptFor(callable $callable, array $eventsToAllow = array (
)) {}
    /** @return string */
    protected static function getFacadeAccessor() {}
}
