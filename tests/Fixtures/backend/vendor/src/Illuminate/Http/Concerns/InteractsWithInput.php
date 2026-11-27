<?php

namespace Illuminate\Http\Concerns;

trait InteractsWithInput
{
    use \Illuminate\Support\Traits\Dumpable, \Illuminate\Support\Traits\InteractsWithData;
    /** @return string|array|null */
    public function server($key = NULL, $default = NULL) {}
    /** @return bool */
    public function hasHeader($key) {}
    /** @param string|array|null $default @return string|array|null */
    public function header($key = NULL, $default = NULL) {}
    /** @return string|null */
    public function bearerToken() {}
    /** @return array */
    public function keys() {}
    /** @return array */
    public function all($keys = NULL) {}
    /** @param mixed $default @return mixed */
    public function input($key = NULL, $default = NULL) {}
    /** @return \Illuminate\Support\Fluent */
    public function fluent($key = NULL, array $default = array (
)) {}
    /** @return string|array|null */
    public function query($key = NULL, $default = NULL) {}
    /** @return string|array|null */
    public function post($key = NULL, $default = NULL) {}
    /** @return bool */
    public function hasCookie($key) {}
    /** @return string|array|null */
    public function cookie($key = NULL, $default = NULL) {}
    /** @return array<string, */
    public function allFiles() {}
    /** @return array<string, */
    protected function convertUploadedFiles(array $files) {}
    /** @return bool */
    public function hasFile($key) {}
    /** @return bool */
    protected function isValidFile($file) {}
    /** @return ($key */
    public function file($key = NULL, $default = NULL) {}
    /** @return mixed */
    protected function data($key = NULL, $default = NULL) {}
    /** @return string|array|null */
    protected function retrieveItem($source, $key, $default) {}
    /** @return $this */
    public function dump($keys = array (
)) {}
    /** @return never */
    public function dd(...$args) {}
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
    /** @param TDefault $default @return TEnum|TDefault */
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
