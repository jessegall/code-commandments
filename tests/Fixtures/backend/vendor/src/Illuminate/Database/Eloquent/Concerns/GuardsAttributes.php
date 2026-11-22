<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait GuardsAttributes
{
    protected $fillable = array (
);
    protected $guarded = array (
  0 => '*',
);
    protected static $unguarded = false;
    protected static $guardableColumns = array (
);
    public function getFillable() {}
    public function fillable(array $fillable) {}
    public function mergeFillable(array $fillable) {}
    public function getGuarded() {}
    public function guard(array $guarded) {}
    public function mergeGuarded(array $guarded) {}
    public static function unguard($state = true) {}
    public static function reguard() {}
    public static function isUnguarded() {}
    public static function unguarded(callable $callback) {}
    public function isFillable($key) {}
    public function isGuarded($key) {}
    protected function isGuardableColumn($key) {}
    public function totallyGuarded() {}
    protected function fillableFromArray(array $attributes) {}
}
