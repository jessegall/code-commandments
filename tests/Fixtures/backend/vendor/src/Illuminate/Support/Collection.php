<?php

namespace Illuminate\Support;

class Collection implements \ArrayAccess, \Illuminate\Contracts\Support\CanBeEscapedWhenCastToString, \Illuminate\Support\Enumerable, \Traversable, \Stringable, \JsonSerializable, \Illuminate\Contracts\Support\Jsonable, \IteratorAggregate, \Countable, \Illuminate\Contracts\Support\Arrayable
{
    use \Illuminate\Support\Traits\EnumeratesValues, \Illuminate\Support\Traits\Macroable, \Illuminate\Support\Traits\TransformsToResourceCollection;
    protected $items = array (
);
    public function __construct($items = array (
)) {}
    /** @return static<int, */
    public static function range($from, $to, $step = 1) {}
    /** @return array<TKey, */
    public function all() {}
    /** @return \Illuminate\Support\LazyCollection<TKey, */
    public function lazy() {}
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
    /** @return static<int, */
    public function crossJoin(...$lists) {}
    /** @return static */
    public function diff($items) {}
    /** @return static */
    public function diffUsing($items, callable $callback) {}
    /** @return static */
    public function diffAssoc($items) {}
    /** @return static */
    public function diffAssocUsing($items, callable $callback) {}
    /** @return static */
    public function diffKeys($items) {}
    /** @return static */
    public function diffKeysUsing($items, callable $callback) {}
    /** @return static */
    public function duplicates($callback = NULL, $strict = false) {}
    /** @return static */
    public function duplicatesStrict($callback = NULL) {}
    /** @return callable(TValue, */
    protected function duplicateComparator($strict) {}
    /** @return static */
    public function except($keys) {}
    /** @return static */
    public function filter(?callable $callback = NULL) {}
    /** @return TValue|TFirstDefault */
    public function first(?callable $callback = NULL, $default = NULL) {}
    /** @return static<int, */
    public function flatten($depth = \Illuminate\Support\INF) {}
    /** @return static<TValue, */
    public function flip() {}
    /** @return $this */
    public function forget($keys) {}
    /** @param TGetDefault|(\Closure(): TGetDefault) $default @return TValue|TGetDefault */
    public function get($key, $default = NULL) {}
    /** @return TValue|TGetOrPutValue */
    public function getOrPut($key, $value) {}
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
    /** @return static */
    public function intersect($items) {}
    /** @return static */
    public function intersectUsing($items, callable $callback) {}
    /** @return static */
    public function intersectAssoc($items) {}
    /** @return static */
    public function intersectAssocUsing($items, callable $callback) {}
    /** @return static */
    public function intersectByKeys($items) {}
    /** @return bool */
    public function isEmpty() {}
    public function containsOneItem(?callable $callback = NULL): bool {}
    public function containsManyItems(?callable $callback = NULL): bool {}
    /** @return TValue|string */
    public function join($glue, $finalGlue = '') {}
    /** @return static<int, */
    public function keys() {}
    /** @return TValue|TLastDefault */
    public function last(?callable $callback = NULL, $default = NULL) {}
    /** @return static<array-key, */
    public function pluck($value, $key = NULL) {}
    /** @return static<TKey, */
    public function map(callable $callback) {}
    /** @return static<TMapToDictionaryKey, */
    public function mapToDictionary(callable $callback) {}
    /** @return static<TMapWithKeysKey, */
    public function mapWithKeys(callable $callback) {}
    /** @return static<TKey, */
    public function merge($items) {}
    /** @return static<TKey, */
    public function mergeRecursive($items) {}
    /** @return static */
    public function multiply(int $multiplier) {}
    /** @return static<TValue, */
    public function combine($values) {}
    /** @return static */
    public function union($items) {}
    /** @return ($step */
    public function nth($step, $offset = 0) {}
    /** @return static */
    public function only($keys) {}
    /** @return static */
    public function select($keys) {}
    /** @return ($count */
    public function pop($count = 1) {}
    /** @return $this */
    public function prepend($value, $key = NULL) {}
    /** @return $this */
    public function push(...$values) {}
    /** @return $this */
    public function unshift(...$values) {}
    /** @return static<TKey|TConcatKey, */
    public function concat($source) {}
    /** @return TValue|TPullDefault */
    public function pull($key, $default = NULL) {}
    /** @return $this */
    public function put($key, $value) {}
    /** @return ($number */
    public function random($number = NULL, $preserveKeys = false) {}
    /** @return static */
    public function replace($items) {}
    /** @return static */
    public function replaceRecursive($items) {}
    /** @return static */
    public function reverse() {}
    /** @return TKey|false */
    public function search($value, $strict = false) {}
    /** @return TValue|null */
    public function before($value, $strict = false) {}
    /** @return TValue|null */
    public function after($value, $strict = false) {}
    /** @return ($count */
    public function shift($count = 1) {}
    /** @return static */
    public function shuffle() {}
    /** @return static<int, */
    public function sliding($size = 2, $step = 1) {}
    /** @return static */
    public function skip($count) {}
    /** @return static */
    public function skipUntil($value) {}
    /** @return static */
    public function skipWhile($value) {}
    /** @return static */
    public function slice($offset, $length = NULL) {}
    /** @return ($numberOfGroups */
    public function split($numberOfGroups) {}
    /** @return ($numberOfGroups */
    public function splitIn($numberOfGroups) {}
    /** @return TValue */
    public function sole($key = NULL, $operator = NULL, $value = NULL) {}
    public function hasSole($key = NULL, $operator = NULL, $value = NULL): bool {}
    /** @return TValue */
    public function firstOrFail($key = NULL, $operator = NULL, $value = NULL) {}
    /** @return ($preserveKeys */
    public function chunk($size, $preserveKeys = true) {}
    /** @return static<int, */
    public function chunkWhile(callable $callback) {}
    /** @return static */
    public function sort($callback = NULL) {}
    /** @return static */
    public function sortDesc($options = \Illuminate\Support\SORT_REGULAR) {}
    /** @return static */
    public function sortBy($callback, $options = \Illuminate\Support\SORT_REGULAR, $descending = false) {}
    /** @return static */
    protected function sortByMany(array $comparisons = array (
), int $options = \Illuminate\Support\SORT_REGULAR) {}
    /** @return static */
    public function sortByDesc($callback, $options = \Illuminate\Support\SORT_REGULAR) {}
    /** @return static */
    public function sortKeys($options = \Illuminate\Support\SORT_REGULAR, $descending = false) {}
    /** @return static */
    public function sortKeysDesc($options = \Illuminate\Support\SORT_REGULAR) {}
    /** @return static */
    public function sortKeysUsing(callable $callback) {}
    /** @return static */
    public function splice($offset, $length = NULL, $replacement = array (
)) {}
    /** @return static */
    public function take($limit) {}
    /** @return static */
    public function takeUntil($value) {}
    /** @return static */
    public function takeWhile($value) {}
    /** @return $this */
    public function transform(callable $callback) {}
    /** @return static */
    public function dot($depth = \Illuminate\Support\INF) {}
    /** @return static */
    public function undot() {}
    /** @return static */
    public function unique($key = NULL, $strict = false) {}
    /** @return static<int, */
    public function values() {}
    /** @return static<int, */
    public function zip($items) {}
    /** @return static<int, */
    public function pad($size, $value) {}
    public function getIterator(): \Traversable {}
    public function count(): int {}
    /** @return static<array-key, */
    public function countBy($countBy = NULL) {}
    /** @return $this */
    public function add($item) {}
    /** @return \Illuminate\Support\Collection<TKey, */
    public function toBase() {}
    public function offsetExists($key): bool {}
    public function offsetGet($key): mixed {}
    public function offsetSet($key, $value): void {}
    public function offsetUnset($key): void {}
}
