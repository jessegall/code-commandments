<?php

namespace Illuminate\Support\Traits;

trait InteractsWithData
{
    abstract public function all($keys = NULL);
    abstract protected function data($key = NULL, $default = NULL);
    public function exists($key) {}
    public function has($key) {}
    public function hasAny($keys) {}
    public function whenHas($key, callable $callback, ?callable $default = NULL) {}
    public function filled($key) {}
    public function isNotFilled($key) {}
    public function anyFilled($keys) {}
    public function whenFilled($key, callable $callback, ?callable $default = NULL) {}
    public function missing($key) {}
    public function whenMissing($key, callable $callback, ?callable $default = NULL) {}
    protected function isEmptyString($key) {}
    public function str($key, $default = NULL) {}
    public function string($key, $default = NULL) {}
    public function boolean($key = NULL, $default = false) {}
    public function integer($key, $default = 0) {}
    public function float($key, $default = 0.0) {}
    public function clamp($key, $min, $max, $default = 0) {}
    public function date($key, $format = NULL, $tz = NULL) {}
    public function interval($key, $unit = NULL) {}
    public function enum($key, $enumClass, $default = NULL) {}
    public function enums($key, $enumClass) {}
    protected function isBackedEnum($enumClass) {}
    public function array($key = NULL) {}
    public function collect($key = NULL) {}
    public function only($keys) {}
    public function except($keys) {}
}
