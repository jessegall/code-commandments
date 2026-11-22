<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait PreventsCircularRecursion
{
    protected static $recursionCache = NULL;
    protected function withoutRecursion($callback, $default = NULL) {}
    protected static function clearRecursiveCallValue($object, string $hash) {}
    protected static function getRecursiveCallStack($object): array {}
    protected static function getRecursionCache() {}
    protected static function setRecursiveCallValue($object, string $hash, $value) {}
}
