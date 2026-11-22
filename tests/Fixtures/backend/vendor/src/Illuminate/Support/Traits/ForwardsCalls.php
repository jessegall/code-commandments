<?php

namespace Illuminate\Support\Traits;

trait ForwardsCalls
{
    protected function forwardCallTo($object, $method, $parameters) {}
    protected function forwardDecoratedCallTo($object, $method, $parameters) {}
    protected static function throwBadMethodCallException($method) {}
}
