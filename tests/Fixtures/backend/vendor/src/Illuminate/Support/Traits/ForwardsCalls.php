<?php

namespace Illuminate\Support\Traits;

trait ForwardsCalls
{
    /** @return mixed */
    protected function forwardCallTo($object, $method, $parameters) {}
    /** @return mixed */
    protected function forwardDecoratedCallTo($object, $method, $parameters) {}
    /** @return never */
    protected static function throwBadMethodCallException($method) {}
}
