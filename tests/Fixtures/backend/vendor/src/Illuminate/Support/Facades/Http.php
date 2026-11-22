<?php

namespace Illuminate\Support\Facades;

class Http extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor() {}
    public static function fake($callback = NULL) {}
    public static function fakeSequence(string $urlPattern = '*') {}
    public static function preventStrayRequests($prevent = true) {}
    public static function stubUrl($url, $callback) {}
}
