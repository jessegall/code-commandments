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
    /** @return array<string> */
    public function getFillable() {}
    /** @return $this */
    public function fillable(array $fillable) {}
    /** @return $this */
    public function mergeFillable(array $fillable) {}
    /** @return array<string> */
    public function getGuarded() {}
    /** @return $this */
    public function guard(array $guarded) {}
    /** @return $this */
    public function mergeGuarded(array $guarded) {}
    /** @return void */
    public static function unguard($state = true) {}
    /** @return void */
    public static function reguard() {}
    /** @return bool */
    public static function isUnguarded() {}
    /** @return TReturn */
    public static function unguarded(callable $callback) {}
    /** @return bool */
    public function isFillable($key) {}
    /** @return bool */
    public function isGuarded($key) {}
    /** @return bool */
    protected function isGuardableColumn($key) {}
    /** @return bool */
    public function totallyGuarded() {}
    /** @return array<string, */
    protected function fillableFromArray(array $attributes) {}
}
