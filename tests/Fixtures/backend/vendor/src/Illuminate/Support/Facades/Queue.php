<?php

namespace Illuminate\Support\Facades;

class Queue extends \Illuminate\Support\Facades\Facade
{
    public static function popUsing($workerName, $callback) {}
    public static function fake($jobsToFake = array (
)) {}
    public static function fakeExcept($jobsToAllow) {}
    public static function fakeFor(callable $callable, array $jobsToFake = array (
)) {}
    public static function fakeExceptFor(callable $callable, array $jobsToAllow = array (
)) {}
    protected static function getFacadeAccessor() {}
}
