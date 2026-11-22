<?php

namespace Illuminate\Support\Facades;

class Event extends \Illuminate\Support\Facades\Facade
{
    public static function fake($eventsToFake = array (
)) {}
    public static function fakeExcept($eventsToAllow) {}
    public static function fakeFor(callable $callable, array $eventsToFake = array (
)) {}
    public static function fakeExceptFor(callable $callable, array $eventsToAllow = array (
)) {}
    protected static function getFacadeAccessor() {}
}
