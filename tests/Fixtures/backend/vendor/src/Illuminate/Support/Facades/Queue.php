<?php

namespace Illuminate\Support\Facades;

class Queue extends \Illuminate\Support\Facades\Facade
{
    /** @return void */
    public static function popUsing($workerName, $callback) {}
    /** @return \Illuminate\Support\Testing\Fakes\QueueFake */
    public static function fake($jobsToFake = array (
)) {}
    /** @return \Illuminate\Support\Testing\Fakes\QueueFake */
    public static function fakeExcept($jobsToAllow) {}
    /** @return mixed */
    public static function fakeFor(callable $callable, array $jobsToFake = array (
)) {}
    /** @return mixed */
    public static function fakeExceptFor(callable $callable, array $jobsToAllow = array (
)) {}
    /** @return string */
    protected static function getFacadeAccessor() {}
}
