<?php

namespace Illuminate\Support;

class LazyCollection implements \Illuminate\Contracts\Support\CanBeEscapedWhenCastToString, \Illuminate\Support\Enumerable, \Traversable, \Stringable, \JsonSerializable, \Illuminate\Contracts\Support\Jsonable, \IteratorAggregate, \Countable, \Illuminate\Contracts\Support\Arrayable
{
    use \Illuminate\Support\Traits\EnumeratesValues, \Illuminate\Support\Traits\Macroable;
    public $source = NULL;
    public function __construct($source = NULL) {}
    /** @return static<TMakeKey, */
    public static function make($items = array (
)) {}
    /** @return ($step */
    public static function range($from, $to, $step = 1) {}
    /** @return array<TKey, */
    public function all() {}
    /** @return static<TKey, */
    public function eager() {}
    /** @return static<TKey, */
    public function remember() {}
    /** @return float|int|null */
    public function median($key = NULL) {}
    /** @return array<int, */
    public function mode($key = NULL) {}
    /** @return static<int, */
    public function collapse() {}
    /** @return static<mixed, */
    public function collapseWithKeys() {}
    /** @return bool */
    public function contains($key, $operator = NULL, $value = NULL) {}
    /** @return bool */
    public function containsStrict($key, $value = NULL) {}
    /** @return bool */
    public function doesntContain($key, $operator = NULL, $value = NULL) {}
    /** @return bool */
    public function doesntContainStrict($key, $operator = NULL, $value = NULL) {}
    public function crossJoin(...$arrays) {}
    /** @return static<array-key, */
    public function countBy($countBy = NULL) {}
    public function diff($items) {}
    public function diffUsing($items, callable $callback) {}
    public function diffAssoc($items) {}
    public function diffAssocUsing($items, callable $callback) {}
    public function diffKeys($items) {}
    public function diffKeysUsing($items, callable $callback) {}
    public function duplicates($callback = NULL, $strict = false) {}
    public function duplicatesStrict($callback = NULL) {}
    public function except($keys) {}
    /** @return static */
    public function filter(?callable $callback = NULL) {}
    /** @return TValue|TFirstDefault */
    public function first(?callable $callback = NULL, $default = NULL) {}
    /** @return static<int, */
    public function flatten($depth = \Illuminate\Support\INF) {}
    /** @return static<TValue, */
    public function flip() {}
    /** @return TValue|TGetDefault */
    public function get($key, $default = NULL) {}
    /** @return static< */
    public function groupBy($groupBy, $preserveKeys = false) {}
    /** @return static<($keyBy */
    public function keyBy($keyBy) {}
    /** @return bool */
    public function has($key) {}
    /** @return bool */
    public function hasAny($key) {}
    /** @return string */
    public function implode($value, $glue = NULL) {}
    public function intersect($items) {}
    public function intersectUsing($items, callable $callback) {}
    public function intersectAssoc($items) {}
    public function intersectAssocUsing($items, callable $callback) {}
    public function intersectByKeys($items) {}
    /** @return bool */
    public function isEmpty() {}
    public function containsOneItem(?callable $callback = NULL): bool {}
    public function containsManyItems(): bool {}
    /** @return string */
    public function join($glue, $finalGlue = '') {}
    /** @return static<int, */
    public function keys() {}
    /** @return TValue|TLastDefault */
    public function last(?callable $callback = NULL, $default = NULL) {}
    /** @return static<array-key, */
    public function pluck($value, $key = NULL) {}
    /** @return static<TKey, */
    public function map(callable $callback) {}
    public function mapToDictionary(callable $callback) {}
    /** @return static<TMapWithKeysKey, */
    public function mapWithKeys(callable $callback) {}
    public function merge($items) {}
    public function mergeRecursive($items) {}
    /** @return static */
    public function multiply(int $multiplier) {}
    /** @return static<TValue, */
    public function combine($values) {}
    public function union($items) {}
    /** @return ($step */
    public function nth($step, $offset = 0) {}
    /** @return static */
    public function only($keys) {}
    /** @return static */
    public function select($keys) {}
    /** @return static<TKey|TConcatKey, */
    public function concat($source) {}
    /** @return static<int, */
    public function random($number = NULL, $preserveKeys = false) {}
    /** @return static */
    public function replace($items) {}
    public function replaceRecursive($items) {}
    public function reverse() {}
    /** @return TKey|false */
    public function search($value, $strict = false) {}
    /** @return TValue|null */
    public function before($value, $strict = false) {}
    /** @return TValue|null */
    public function after($value, $strict = false) {}
    public function shuffle() {}
    /** @return static<int, */
    public function sliding($size = 2, $step = 1) {}
    /** @return static */
    public function skip($count) {}
    /** @return static */
    public function skipUntil($value) {}
    /** @return static */
    public function skipWhile($value) {}
    public function slice($offset, $length = NULL) {}
    public function split($numberOfGroups) {}
    /** @return TValue */
    public function sole($key = NULL, $operator = NULL, $value = NULL) {}
    public function hasSole($key = NULL, $operator = NULL, $value = NULL): bool {}
    /** @return TValue */
    public function firstOrFail($key = NULL, $operator = NULL, $value = NULL) {}
    /** @return ($preserveKeys */
    public function chunk($size, $preserveKeys = true) {}
    /** @return ($numberOfGroups */
    public function splitIn($numberOfGroups) {}
    /** @return static<int, */
    public function chunkWhile(callable $callback) {}
    public function sort($callback = NULL) {}
    public function sortDesc($options = \Illuminate\Support\SORT_REGULAR) {}
    public function sortBy($callback, $options = \Illuminate\Support\SORT_REGULAR, $descending = false) {}
    public function sortByDesc($callback, $options = \Illuminate\Support\SORT_REGULAR) {}
    public function sortKeys($options = \Illuminate\Support\SORT_REGULAR, $descending = false) {}
    public function sortKeysDesc($options = \Illuminate\Support\SORT_REGULAR) {}
    public function sortKeysUsing(callable $callback) {}
    /** @return static<TKey, */
    public function take($limit) {}
    /** @return static<TKey, */
    public function takeUntil($value) {}
    /** @return static<TKey, */
    public function takeUntilTimeout(\DateTimeInterface $timeout, ?callable $callback = NULL) {}
    /** @return static<TKey, */
    public function takeWhile($value) {}
    /** @return static<TKey, */
    public function tapEach(callable $callback) {}
    /** @return static<TKey, */
    public function throttle(float $seconds) {}
    /** @return static */
    public function dot($depth = \Illuminate\Support\INF) {}
    public function undot() {}
    /** @return static<TKey, */
    public function unique($key = NULL, $strict = false) {}
    /** @return static<int, */
    public function values() {}
    /** @return static<TKey, */
    public function withHeartbeat(\DateInterval|int $interval, callable $callback) {}
    protected function intervalSeconds(\DateInterval $interval): int {}
    /** @return static<int, */
    public function zip($items) {}
    public function pad($size, $value) {}
    public function getIterator(): \Traversable {}
    public function count(): int {}
    /** @return \Traversable<TIteratorKey, */
    protected function makeIterator($source) {}
    /** @return array{string[],string[]|null} */
    protected function explodePluckParameters($value, $key) {}
    /** @return static */
    protected function passthru($method, array $params) {}
    /** @return int */
    protected function now() {}
    /** @return float */
    protected function preciseNow() {}
    /** @return void */
    protected function usleep(int $microseconds) {}
}
