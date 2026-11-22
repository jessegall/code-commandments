<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HasTimestamps
{
    public $timestamps = true;
    protected static $ignoreTimestampsOn = array (
);
    public function touch($attribute = NULL) {}
    public function touchQuietly($attribute = NULL) {}
    public function updateTimestamps() {}
    public function setCreatedAt($value) {}
    public function setUpdatedAt($value) {}
    public function freshTimestamp() {}
    public function freshTimestampString() {}
    public function usesTimestamps() {}
    public function getCreatedAtColumn() {}
    public function getUpdatedAtColumn() {}
    public function getQualifiedCreatedAtColumn() {}
    public function getQualifiedUpdatedAtColumn() {}
    public static function withoutTimestamps(callable $callback) {}
    public static function withoutTimestampsOn($models, $callback) {}
    public static function isIgnoringTimestamps($class = NULL) {}
}
