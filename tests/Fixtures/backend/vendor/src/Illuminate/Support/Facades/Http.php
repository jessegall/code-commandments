<?php

namespace Illuminate\Support\Facades;

class Http extends \Illuminate\Support\Facades\Facade
{
    /** @return string */
    protected static function getFacadeAccessor() {}
    /** @return \Illuminate\Http\Client\Factory */
    public static function fake($callback = NULL) {}
    /** @return \Illuminate\Http\Client\ResponseSequence */
    public static function fakeSequence(string $urlPattern = '*') {}
    /** @return \Illuminate\Http\Client\Factory */
    public static function preventStrayRequests($prevent = true) {}
    /** @return \Illuminate\Http\Client\Factory */
    public static function stubUrl($url, $callback) {}
}
