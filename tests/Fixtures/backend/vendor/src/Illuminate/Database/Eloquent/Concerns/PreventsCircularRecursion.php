<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait PreventsCircularRecursion
{
    protected static $recursionCache = NULL;
    /** @return mixed */
    protected function withoutRecursion($callback, $default = NULL) {}
    protected static function clearRecursiveCallValue($object, string $hash) {}
    protected static function getRecursiveCallStack($object): array {}
    /** @return WeakMap */
    protected static function getRecursionCache() {}
    /** @return mixed */
    protected static function setRecursiveCallValue($object, string $hash, $value) {}
}
