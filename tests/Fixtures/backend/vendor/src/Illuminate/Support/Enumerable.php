<?php

namespace Illuminate\Support;

interface Enumerable extends \Illuminate\Contracts\Support\Arrayable, \Countable, \IteratorAggregate, \Illuminate\Contracts\Support\Jsonable, \JsonSerializable, \Stringable, \Traversable
{
    /** @return static<TMakeKey, */
    public static function make($items = array (
));
    /** @return static */
    public static function times($number, ?callable $callback = NULL);
    /** @return static */
    public static function range($from, $to, $step = 1);
    /** @return static<array-key, */
    public static function wrap($value);
    /** @return array<TUnwrapKey, */
    public static function unwrap($value);
    /** @return static */
    public static function empty();
    /** @return array */
    public function all();
    /** @return float|int|null */
    public function average($callback = NULL);
    /** @return float|int|null */
    public function median($key = NULL);
    /** @return array<int, */
    public function mode($key = NULL);
    /** @return static<int, */
    public function collapse();
    /** @return bool */
    public function some($key, $operator = NULL, $value = NULL);
    /** @return bool */
    public function containsStrict($key, $value = NULL);
    /** @return float|int|null */
    public function avg($callback = NULL);
    /** @return bool */
    public function contains($key, $operator = NULL, $value = NULL);
    /** @return bool */
    public function doesntContain($key, $operator = NULL, $value = NULL);
    /** @return static<int, */
    public function crossJoin(...$lists);
    /** @return never */
    public function dd(...$args);
    /** @return $this */
    public function dump(...$args);
    /** @return static */
    public function diff($items);
    /** @return static */
    public function diffUsing($items, callable $callback);
    /** @return static */
    public function diffAssoc($items);
    /** @return static */
    public function diffAssocUsing($items, callable $callback);
    /** @return static */
    public function diffKeys($items);
    /** @return static */
    public function diffKeysUsing($items, callable $callback);
    /** @return static */
    public function duplicates($callback = NULL, $strict = false);
    /** @return static */
    public function duplicatesStrict($callback = NULL);
    /** @return $this */
    public function each(callable $callback);
    /** @return static */
    public function eachSpread(callable $callback);
    /** @return bool */
    public function every($key, $operator = NULL, $value = NULL);
    /** @return static */
    public function except($keys);
    /** @return static */
    public function filter(?callable $callback = NULL);
    /** @return $this|TWhenReturnType */
    public function when($value, ?callable $callback = NULL, ?callable $default = NULL);
    /** @return $this|TWhenEmptyReturnType */
    public function whenEmpty(callable $callback, ?callable $default = NULL);
    /** @return $this|TWhenNotEmptyReturnType */
    public function whenNotEmpty(callable $callback, ?callable $default = NULL);
    /** @return $this|TUnlessReturnType */
    public function unless($value, callable $callback, ?callable $default = NULL);
    /** @return $this|TUnlessEmptyReturnType */
    public function unlessEmpty(callable $callback, ?callable $default = NULL);
    /** @return $this|TUnlessNotEmptyReturnType */
    public function unlessNotEmpty(callable $callback, ?callable $default = NULL);
    /** @return static */
    public function where($key, $operator = NULL, $value = NULL);
    /** @return static */
    public function whereNull($key = NULL);
    /** @return static */
    public function whereNotNull($key = NULL);
    /** @return static */
    public function whereStrict($key, $value);
    /** @return static */
    public function whereIn($key, $values, $strict = false);
    /** @return static */
    public function whereInStrict($key, $values);
    /** @return static */
    public function whereBetween($key, $values);
    /** @return static */
    public function whereNotBetween($key, $values);
    /** @return static */
    public function whereNotIn($key, $values, $strict = false);
    /** @return static */
    public function whereNotInStrict($key, $values);
    /** @return static<TKey, */
    public function whereInstanceOf($type);
    /** @return TValue|TFirstDefault */
    public function first(?callable $callback = NULL, $default = NULL);
    /** @return TValue|null */
    public function firstWhere($key, $operator = NULL, $value = NULL);
    /** @return static */
    public function flatten($depth = \Illuminate\Support\INF);
    /** @return static<TValue, */
    public function flip();
    /** @return TValue|TGetDefault */
    public function get($key, $default = NULL);
    /** @return static<($groupBy */
    public function groupBy($groupBy, $preserveKeys = false);
    /** @return static<($keyBy */
    public function keyBy($keyBy);
    /** @return bool */
    public function has($key);
    /** @return bool */
    public function hasAny($key);
    /** @return string */
    public function implode($value, $glue = NULL);
    /** @return static */
    public function intersect($items);
    /** @return static */
    public function intersectUsing($items, callable $callback);
    /** @return static */
    public function intersectAssoc($items);
    /** @return static */
    public function intersectAssocUsing($items, callable $callback);
    /** @return static */
    public function intersectByKeys($items);
    /** @return bool */
    public function isEmpty();
    /** @return bool */
    public function isNotEmpty();
    /** @return bool */
    public function containsOneItem();
    /** @return bool */
    public function containsManyItems();
    /** @return string */
    public function join($glue, $finalGlue = '');
    /** @return static<int, */
    public function keys();
    /** @return TValue|TLastDefault */
    public function last(?callable $callback = NULL, $default = NULL);
    /** @return static<TKey, */
    public function map(callable $callback);
    /** @return static */
    public function mapSpread(callable $callback);
    /** @return static<TMapToDictionaryKey, */
    public function mapToDictionary(callable $callback);
    /** @return static<TMapToGroupsKey, */
    public function mapToGroups(callable $callback);
    /** @return static<TMapWithKeysKey, */
    public function mapWithKeys(callable $callback);
    /** @return static<TFlatMapKey, */
    public function flatMap(callable $callback);
    /** @return static<TKey, */
    public function mapInto($class);
    /** @return static<TKey, */
    public function merge($items);
    /** @return static<TKey, */
    public function mergeRecursive($items);
    /** @return static<TValue, */
    public function combine($values);
    /** @return static */
    public function union($items);
    /** @return mixed */
    public function min($callback = NULL);
    /** @return mixed */
    public function max($callback = NULL);
    /** @return static */
    public function nth($step, $offset = 0);
    /** @return static */
    public function only($keys);
    /** @return static */
    public function forPage($page, $perPage);
    /** @return static<int<0, */
    public function partition($key, $operator = NULL, $value = NULL);
    /** @return static<TKey|TConcatKey, */
    public function concat($source);
    /** @return static<int, */
    public function random($number = NULL);
    /** @return TReduceInitial|TReduceReturnType */
    public function reduce(callable $callback, $initial = NULL);
    /** @return array */
    public function reduceSpread(callable $callback, ...$initial);
    /** @return static */
    public function replace($items);
    /** @return static */
    public function replaceRecursive($items);
    /** @return static */
    public function reverse();
    /** @return TKey|bool */
    public function search($value, $strict = false);
    /** @return TValue|null */
    public function before($value, $strict = false);
    /** @return TValue|null */
    public function after($value, $strict = false);
    /** @return static */
    public function shuffle();
    /** @return static<int, */
    public function sliding($size = 2, $step = 1);
    /** @return static */
    public function skip($count);
    /** @return static */
    public function skipUntil($value);
    /** @return static */
    public function skipWhile($value);
    /** @return static */
    public function slice($offset, $length = NULL);
    /** @return static<int, */
    public function split($numberOfGroups);
    /** @return TValue */
    public function sole($key = NULL, $operator = NULL, $value = NULL);
    /** @return TValue */
    public function firstOrFail($key = NULL, $operator = NULL, $value = NULL);
    /** @return static<int, */
    public function chunk($size);
    /** @return static<int, */
    public function chunkWhile(callable $callback);
    /** @return static<int, */
    public function splitIn($numberOfGroups);
    /** @return static */
    public function sort($callback = NULL);
    /** @return static */
    public function sortDesc($options = \Illuminate\Support\SORT_REGULAR);
    /** @return static */
    public function sortBy($callback, $options = \Illuminate\Support\SORT_REGULAR, $descending = false);
    /** @return static */
    public function sortByDesc($callback, $options = \Illuminate\Support\SORT_REGULAR);
    /** @return static */
    public function sortKeys($options = \Illuminate\Support\SORT_REGULAR, $descending = false);
    /** @return static */
    public function sortKeysDesc($options = \Illuminate\Support\SORT_REGULAR);
    /** @return static */
    public function sortKeysUsing(callable $callback);
    /** @return mixed */
    public function sum($callback = NULL);
    /** @return static */
    public function take($limit);
    /** @return static */
    public function takeUntil($value);
    /** @return static */
    public function takeWhile($value);
    /** @return $this */
    public function tap(callable $callback);
    /** @return TPipeReturnType */
    public function pipe(callable $callback);
    /** @return TPipeIntoValue */
    public function pipeInto($class);
    /** @return mixed */
    public function pipeThrough($pipes);
    /** @return static<array-key, */
    public function pluck($value, $key = NULL);
    /** @return static */
    public function reject($callback = true);
    /** @return static */
    public function undot();
    /** @return static */
    public function unique($key = NULL, $strict = false);
    /** @return static */
    public function uniqueStrict($key = NULL);
    /** @return static<int, */
    public function values();
    /** @return static<int, */
    public function pad($size, $value);
    public function getIterator(): \Traversable;
    public function count(): int;
    /** @return static<array-key, */
    public function countBy($countBy = NULL);
    /** @return static<int, */
    public function zip($items);
    /** @return \Illuminate\Support\Collection<TKey, */
    public function collect();
    /** @return array<TKey, */
    public function toArray();
    public function jsonSerialize(): mixed;
    /** @return string */
    public function toJson($options = 0);
    /** @return string */
    public function toPrettyJson(int $options = 0);
    /** @return \CachingIterator */
    public function getCachingIterator($flags = \CachingIterator::CALL_TOSTRING);
    public function __toString(): string;
    /** @return $this */
    public function escapeWhenCastingToString($escape = true);
    /** @return void */
    public static function proxy($method);
    /** @return mixed */
    public function __get($key);
}
