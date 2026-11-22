<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HasTimestamps
{
    public $timestamps = true;
    protected static $ignoreTimestampsOn = array (
);
    /** @return bool */
    public function touch($attribute = NULL) {}
    /** @return bool */
    public function touchQuietly($attribute = NULL) {}
    /** @return $this */
    public function updateTimestamps() {}
    /** @return $this */
    public function setCreatedAt($value) {}
    /** @return $this */
    public function setUpdatedAt($value) {}
    /** @return \Illuminate\Support\Carbon */
    public function freshTimestamp() {}
    /** @return string */
    public function freshTimestampString() {}
    /** @return bool */
    public function usesTimestamps() {}
    /** @return string|null */
    public function getCreatedAtColumn() {}
    /** @return string|null */
    public function getUpdatedAtColumn() {}
    /** @return string|null */
    public function getQualifiedCreatedAtColumn() {}
    /** @return string|null */
    public function getQualifiedUpdatedAtColumn() {}
    /** @return mixed */
    public static function withoutTimestamps(callable $callback) {}
    /** @return mixed */
    public static function withoutTimestampsOn($models, $callback) {}
    /** @return bool */
    public static function isIgnoringTimestamps($class = NULL) {}
}
