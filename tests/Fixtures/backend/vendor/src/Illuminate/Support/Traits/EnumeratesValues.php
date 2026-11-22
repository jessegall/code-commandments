<?php

namespace Illuminate\Support\Traits;

trait EnumeratesValues
{
    use \Illuminate\Support\Traits\Conditionable;
    protected $escapeWhenCastingToString = false;
    protected static $proxies = array (
  0 => 'average',
  1 => 'avg',
  2 => 'contains',
  3 => 'doesntContain',
  4 => 'each',
  5 => 'every',
  6 => 'filter',
  7 => 'first',
  8 => 'flatMap',
  9 => 'groupBy',
  10 => 'hasMany',
  11 => 'hasSole',
  12 => 'keyBy',
  13 => 'last',
  14 => 'map',
  15 => 'max',
  16 => 'min',
  17 => 'partition',
  18 => 'percentage',
  19 => 'reject',
  20 => 'skipUntil',
  21 => 'skipWhile',
  22 => 'some',
  23 => 'sortBy',
  24 => 'sortByDesc',
  25 => 'sum',
  26 => 'takeUntil',
  27 => 'takeWhile',
  28 => 'unique',
  29 => 'unless',
  30 => 'until',
  31 => 'when',
);
    public static function make($items = array (
)) {}
    public static function wrap($value) {}
    public static function unwrap($value) {}
    public static function empty() {}
    public static function times($number, ?callable $callback = NULL) {}
    public static function fromJson($json, $depth = 512, $flags = 0) {}
    public function avg($callback = NULL) {}
    public function average($callback = NULL) {}
    public function some($key, $operator = NULL, $value = NULL) {}
    public function dd(...$args) {}
    public function dump(...$args) {}
    public function each(callable $callback) {}
    public function eachSpread(callable $callback) {}
    public function every($key, $operator = NULL, $value = NULL) {}
    public function firstWhere($key, $operator = NULL, $value = NULL) {}
    public function hasMany($key = NULL, $operator = NULL, $value = NULL): bool {}
    public function value($key, $default = NULL) {}
    public function ensure($type) {}
    public function isNotEmpty() {}
    public function mapSpread(callable $callback) {}
    public function mapToGroups(callable $callback) {}
    public function flatMap(callable $callback) {}
    public function mapInto($class) {}
    public function min($callback = NULL) {}
    public function max($callback = NULL) {}
    public function forPage($page, $perPage) {}
    public function partition($key, $operator = NULL, $value = NULL) {}
    public function percentage(callable $callback, int $precision = 2) {}
    public function sum($callback = NULL) {}
    public function whenEmpty(callable $callback, ?callable $default = NULL) {}
    public function whenNotEmpty(callable $callback, ?callable $default = NULL) {}
    public function unlessEmpty(callable $callback, ?callable $default = NULL) {}
    public function unlessNotEmpty(callable $callback, ?callable $default = NULL) {}
    public function where($key, $operator = NULL, $value = NULL) {}
    public function whereNull($key = NULL) {}
    public function whereNotNull($key = NULL) {}
    public function whereStrict($key, $value) {}
    public function whereIn($key, $values, $strict = false) {}
    public function whereInStrict($key, $values) {}
    public function whereBetween($key, $values) {}
    public function whereNotBetween($key, $values) {}
    public function whereNotIn($key, $values, $strict = false) {}
    public function whereNotInStrict($key, $values) {}
    public function whereInstanceOf($type) {}
    public function pipe(callable $callback) {}
    public function pipeInto($class) {}
    public function pipeThrough($callbacks) {}
    public function reduce(callable $callback, $initial = NULL) {}
    public function reduceSpread(callable $callback, ...$initial) {}
    public function reduceWithKeys(callable $callback, $initial = NULL) {}
    public function reject($callback = true) {}
    public function tap(callable $callback) {}
    public function unique($key = NULL, $strict = false) {}
    public function uniqueStrict($key = NULL) {}
    public function collect() {}
    public function toArray() {}
    public function jsonSerialize(): array {}
    public function toJson($options = 0) {}
    public function toPrettyJson(int $options = 0) {}
    public function getCachingIterator($flags = \CachingIterator::CALL_TOSTRING) {}
    public function __toString(): string {}
    public function escapeWhenCastingToString($escape = true) {}
    public static function proxy($method) {}
    public function __get($key) {}
    protected function getArrayableItems($items) {}
    protected function operatorForWhere($key, $operator = NULL, $value = NULL) {}
    protected function useAsCallable($value) {}
    protected function valueRetriever($value) {}
    protected function equality($value) {}
    protected function negate(\Closure $callback) {}
    protected function identity() {}
    public function when($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
    public function unless($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
}
