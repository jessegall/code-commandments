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
    /** @return static<TMakeKey, */
    public static function make($items = array (
)) {}
    /** @return static<array-key, */
    public static function wrap($value) {}
    /** @return array<TUnwrapKey, */
    public static function unwrap($value) {}
    /** @return static */
    public static function empty() {}
    /** @return static<int, */
    public static function times($number, ?callable $callback = NULL) {}
    /** @return static<TKey, */
    public static function fromJson($json, $depth = 512, $flags = 0) {}
    /** @return float|int|null */
    public function avg($callback = NULL) {}
    /** @return float|int|null */
    public function average($callback = NULL) {}
    /** @return bool */
    public function some($key, $operator = NULL, $value = NULL) {}
    /** @return never */
    public function dd(...$args) {}
    /** @return $this */
    public function dump(...$args) {}
    /** @return $this */
    public function each(callable $callback) {}
    /** @return static */
    public function eachSpread(callable $callback) {}
    /** @return bool */
    public function every($key, $operator = NULL, $value = NULL) {}
    /** @param mixed $value @return TValue|null */
    public function firstWhere($key, $operator = NULL, $value = NULL) {}
    public function hasMany($key = NULL, $operator = NULL, $value = NULL): bool {}
    /** @return TValue|TValueDefault */
    public function value($key, $default = NULL) {}
    /** @return static<TKey, */
    public function ensure($type) {}
    /** @return bool */
    public function isNotEmpty() {}
    /** @return static<TKey, */
    public function mapSpread(callable $callback) {}
    /** @return static<TMapToGroupsKey, */
    public function mapToGroups(callable $callback) {}
    /** @return static<TFlatMapKey, */
    public function flatMap(callable $callback) {}
    /** @return static<TKey, */
    public function mapInto($class) {}
    /** @return mixed */
    public function min($callback = NULL) {}
    /** @return mixed */
    public function max($callback = NULL) {}
    /** @return static */
    public function forPage($page, $perPage) {}
    /** @return static<int<0, */
    public function partition($key, $operator = NULL, $value = NULL) {}
    /** @return float|null */
    public function percentage(callable $callback, int $precision = 2) {}
    /** @return ($callback */
    public function sum($callback = NULL) {}
    /** @return $this|TWhenEmptyReturnType */
    public function whenEmpty(callable $callback, ?callable $default = NULL) {}
    /** @return $this|TWhenNotEmptyReturnType */
    public function whenNotEmpty(callable $callback, ?callable $default = NULL) {}
    /** @return $this|TUnlessEmptyReturnType */
    public function unlessEmpty(callable $callback, ?callable $default = NULL) {}
    /** @return $this|TUnlessNotEmptyReturnType */
    public function unlessNotEmpty(callable $callback, ?callable $default = NULL) {}
    /** @return static */
    public function where($key, $operator = NULL, $value = NULL) {}
    /** @return static */
    public function whereNull($key = NULL) {}
    /** @return static */
    public function whereNotNull($key = NULL) {}
    /** @return static */
    public function whereStrict($key, $value) {}
    /** @return static */
    public function whereIn($key, $values, $strict = false) {}
    /** @return static */
    public function whereInStrict($key, $values) {}
    /** @return static */
    public function whereBetween($key, $values) {}
    /** @return static */
    public function whereNotBetween($key, $values) {}
    /** @return static */
    public function whereNotIn($key, $values, $strict = false) {}
    /** @return static */
    public function whereNotInStrict($key, $values) {}
    /** @return static<TKey, */
    public function whereInstanceOf($type) {}
    /** @return TPipeReturnType */
    public function pipe(callable $callback) {}
    /** @return TPipeIntoValue */
    public function pipeInto($class) {}
    /** @return mixed */
    public function pipeThrough($callbacks) {}
    /** @return TReduceReturnType */
    public function reduce(callable $callback, $initial = NULL) {}
    /** @return array */
    public function reduceSpread(callable $callback, ...$initial) {}
    /** @return TReduceWithKeysReturnType */
    public function reduceWithKeys(callable $callback, $initial = NULL) {}
    /** @return static */
    public function reject($callback = true) {}
    /** @return $this */
    public function tap(callable $callback) {}
    /** @return static */
    public function unique($key = NULL, $strict = false) {}
    /** @return static */
    public function uniqueStrict($key = NULL) {}
    /** @return \Illuminate\Support\Collection<TKey, */
    public function collect() {}
    /** @return array<TKey, */
    public function toArray() {}
    public function jsonSerialize(): array {}
    /** @return string */
    public function toJson($options = 0) {}
    /** @return string */
    public function toPrettyJson(int $options = 0) {}
    /** @return \CachingIterator */
    public function getCachingIterator($flags = \CachingIterator::CALL_TOSTRING) {}
    public function __toString(): string {}
    /** @return $this */
    public function escapeWhenCastingToString($escape = true) {}
    /** @return void */
    public static function proxy($method) {}
    /** @return mixed */
    public function __get($key) {}
    /** @return array<TKey, */
    protected function getArrayableItems($items) {}
    /** @return \Closure */
    protected function operatorForWhere($key, $operator = NULL, $value = NULL) {}
    /** @return bool */
    protected function useAsCallable($value) {}
    /** @return callable */
    protected function valueRetriever($value) {}
    /** @return \Closure(mixed): */
    protected function equality($value) {}
    /** @return \Closure */
    protected function negate(\Closure $callback) {}
    /** @return \Closure(TValue): */
    protected function identity() {}
    /** @return $this|TWhenReturnType */
    public function when($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
    /** @return $this|TUnlessReturnType */
    public function unless($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
}
