<?php

namespace Illuminate\Support;

class Collection implements \ArrayAccess, \Illuminate\Contracts\Support\CanBeEscapedWhenCastToString, \Illuminate\Support\Enumerable, \Traversable, \Stringable, \JsonSerializable, \Illuminate\Contracts\Support\Jsonable, \IteratorAggregate, \Countable, \Illuminate\Contracts\Support\Arrayable
{
    use \Illuminate\Support\Traits\EnumeratesValues, \Illuminate\Support\Traits\Macroable, \Illuminate\Support\Traits\TransformsToResourceCollection;
    protected $items = array (
);
    public function __construct($items = array (
)) {}
    public static function range($from, $to, $step = 1) {}
    public function all() {}
    public function lazy() {}
    public function median($key = NULL) {}
    public function mode($key = NULL) {}
    public function collapse() {}
    public function collapseWithKeys() {}
    public function contains($key, $operator = NULL, $value = NULL) {}
    public function containsStrict($key, $value = NULL) {}
    public function doesntContain($key, $operator = NULL, $value = NULL) {}
    public function doesntContainStrict($key, $operator = NULL, $value = NULL) {}
    public function crossJoin(...$lists) {}
    public function diff($items) {}
    public function diffUsing($items, callable $callback) {}
    public function diffAssoc($items) {}
    public function diffAssocUsing($items, callable $callback) {}
    public function diffKeys($items) {}
    public function diffKeysUsing($items, callable $callback) {}
    public function duplicates($callback = NULL, $strict = false) {}
    public function duplicatesStrict($callback = NULL) {}
    protected function duplicateComparator($strict) {}
    public function except($keys) {}
    public function filter(?callable $callback = NULL) {}
    public function first(?callable $callback = NULL, $default = NULL) {}
    public function flatten($depth = \Illuminate\Support\INF) {}
    public function flip() {}
    public function forget($keys) {}
    public function get($key, $default = NULL) {}
    public function getOrPut($key, $value) {}
    public function groupBy($groupBy, $preserveKeys = false) {}
    public function keyBy($keyBy) {}
    public function has($key) {}
    public function hasAny($key) {}
    public function implode($value, $glue = NULL) {}
    public function intersect($items) {}
    public function intersectUsing($items, callable $callback) {}
    public function intersectAssoc($items) {}
    public function intersectAssocUsing($items, callable $callback) {}
    public function intersectByKeys($items) {}
    public function isEmpty() {}
    public function containsOneItem(?callable $callback = NULL): bool {}
    public function containsManyItems(?callable $callback = NULL): bool {}
    public function join($glue, $finalGlue = '') {}
    public function keys() {}
    public function last(?callable $callback = NULL, $default = NULL) {}
    public function pluck($value, $key = NULL) {}
    public function map(callable $callback) {}
    public function mapToDictionary(callable $callback) {}
    public function mapWithKeys(callable $callback) {}
    public function merge($items) {}
    public function mergeRecursive($items) {}
    public function multiply(int $multiplier) {}
    public function combine($values) {}
    public function union($items) {}
    public function nth($step, $offset = 0) {}
    public function only($keys) {}
    public function select($keys) {}
    public function pop($count = 1) {}
    public function prepend($value, $key = NULL) {}
    public function push(...$values) {}
    public function unshift(...$values) {}
    public function concat($source) {}
    public function pull($key, $default = NULL) {}
    public function put($key, $value) {}
    public function random($number = NULL, $preserveKeys = false) {}
    public function replace($items) {}
    public function replaceRecursive($items) {}
    public function reverse() {}
    public function search($value, $strict = false) {}
    public function before($value, $strict = false) {}
    public function after($value, $strict = false) {}
    public function shift($count = 1) {}
    public function shuffle() {}
    public function sliding($size = 2, $step = 1) {}
    public function skip($count) {}
    public function skipUntil($value) {}
    public function skipWhile($value) {}
    public function slice($offset, $length = NULL) {}
    public function split($numberOfGroups) {}
    public function splitIn($numberOfGroups) {}
    public function sole($key = NULL, $operator = NULL, $value = NULL) {}
    public function hasSole($key = NULL, $operator = NULL, $value = NULL): bool {}
    public function firstOrFail($key = NULL, $operator = NULL, $value = NULL) {}
    public function chunk($size, $preserveKeys = true) {}
    public function chunkWhile(callable $callback) {}
    public function sort($callback = NULL) {}
    public function sortDesc($options = \Illuminate\Support\SORT_REGULAR) {}
    public function sortBy($callback, $options = \Illuminate\Support\SORT_REGULAR, $descending = false) {}
    protected function sortByMany(array $comparisons = array (
), int $options = \Illuminate\Support\SORT_REGULAR) {}
    public function sortByDesc($callback, $options = \Illuminate\Support\SORT_REGULAR) {}
    public function sortKeys($options = \Illuminate\Support\SORT_REGULAR, $descending = false) {}
    public function sortKeysDesc($options = \Illuminate\Support\SORT_REGULAR) {}
    public function sortKeysUsing(callable $callback) {}
    public function splice($offset, $length = NULL, $replacement = array (
)) {}
    public function take($limit) {}
    public function takeUntil($value) {}
    public function takeWhile($value) {}
    public function transform(callable $callback) {}
    public function dot($depth = \Illuminate\Support\INF) {}
    public function undot() {}
    public function unique($key = NULL, $strict = false) {}
    public function values() {}
    public function zip($items) {}
    public function pad($size, $value) {}
    public function getIterator(): \Traversable {}
    public function count(): int {}
    public function countBy($countBy = NULL) {}
    public function add($item) {}
    public function toBase() {}
    public function offsetExists($key): bool {}
    public function offsetGet($key): mixed {}
    public function offsetSet($key, $value): void {}
    public function offsetUnset($key): void {}
}
