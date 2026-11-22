<?php

namespace Illuminate\Support\Traits;

trait InteractsWithData
{
    /** @return array */
    abstract public function all($keys = NULL);
    /** @return mixed */
    abstract protected function data($key = NULL, $default = NULL);
    /** @return bool */
    public function exists($key) {}
    /** @return bool */
    public function has($key) {}
    /** @return bool */
    public function hasAny($keys) {}
    /** @return $this|mixed */
    public function whenHas($key, callable $callback, ?callable $default = NULL) {}
    /** @return bool */
    public function filled($key) {}
    /** @return bool */
    public function isNotFilled($key) {}
    /** @return bool */
    public function anyFilled($keys) {}
    /** @return $this|mixed */
    public function whenFilled($key, callable $callback, ?callable $default = NULL) {}
    /** @return bool */
    public function missing($key) {}
    /** @return $this|mixed */
    public function whenMissing($key, callable $callback, ?callable $default = NULL) {}
    /** @return bool */
    protected function isEmptyString($key) {}
    /** @return \Illuminate\Support\Stringable */
    public function str($key, $default = NULL) {}
    /** @return \Illuminate\Support\Stringable */
    public function string($key, $default = NULL) {}
    /** @return bool */
    public function boolean($key = NULL, $default = false) {}
    /** @return int */
    public function integer($key, $default = 0) {}
    /** @return float */
    public function float($key, $default = 0.0) {}
    /** @return float|int */
    public function clamp($key, $min, $max, $default = 0) {}
    /** @return \Illuminate\Support\Carbon|null */
    public function date($key, $format = NULL, $tz = NULL) {}
    /** @return \Carbon\CarbonInterval|null */
    public function interval($key, $unit = NULL) {}
    /** @return TEnum|TDefault */
    public function enum($key, $enumClass, $default = NULL) {}
    /** @return TEnum[] */
    public function enums($key, $enumClass) {}
    /** @return bool */
    protected function isBackedEnum($enumClass) {}
    /** @return array */
    public function array($key = NULL) {}
    /** @return \Illuminate\Support\Collection */
    public function collect($key = NULL) {}
    /** @return array */
    public function only($keys) {}
    /** @return array */
    public function except($keys) {}
}
