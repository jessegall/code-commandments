<?php

namespace Illuminate\Pagination;

abstract class AbstractCursorPaginator implements \Illuminate\Contracts\Support\Htmlable, \Stringable
{
    use \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Support\Traits\Tappable, \Illuminate\Support\Traits\TransformsToResourceCollection;
    protected $items = NULL;
    protected $perPage = NULL;
    protected $path = '/';
    protected $query = array (
);
    protected $fragment = NULL;
    protected $cursorName = 'cursor';
    protected $cursor = NULL;
    protected $parameters = NULL;
    protected $options = NULL;
    protected static $currentCursorResolver = NULL;
    /** @return string */
    public function url($cursor) {}
    /** @return string|null */
    public function previousPageUrl() {}
    /** @return string|null */
    public function nextPageUrl() {}
    /** @return \Illuminate\Pagination\Cursor|null */
    public function previousCursor() {}
    /** @return \Illuminate\Pagination\Cursor|null */
    public function nextCursor() {}
    /** @return \Illuminate\Pagination\Cursor */
    public function getCursorForItem($item, $isNext = true) {}
    /** @return array */
    public function getParametersForItem($item) {}
    /** @return string|null */
    protected function getPivotParameterForItem($item, $parameterName) {}
    /** @return mixed */
    protected function ensureParameterIsPrimitive($parameter) {}
    /** @return $this|string|null */
    public function fragment($fragment = NULL) {}
    /** @return $this */
    public function appends($key, $value = NULL) {}
    /** @return $this */
    protected function appendArray(array $keys) {}
    /** @return $this */
    public function withQueryString() {}
    /** @return $this */
    protected function addQuery($key, $value) {}
    /** @return string */
    protected function buildFragment() {}
    /** @return $this */
    public function loadMorph($relation, $relations) {}
    /** @return $this */
    public function loadMorphCount($relation, $relations) {}
    /** @return array<TKey, */
    public function items() {}
    /** @return $this */
    public function through(callable $callback) {}
    /** @return int */
    public function perPage() {}
    /** @return \Illuminate\Pagination\Cursor|null */
    public function cursor() {}
    /** @return string */
    public function getCursorName() {}
    /** @return $this */
    public function setCursorName($name) {}
    /** @return $this */
    public function withPath($path) {}
    /** @return $this */
    public function setPath($path) {}
    /** @return string|null */
    public function path() {}
    /** @return \Illuminate\Pagination\Cursor|null */
    public static function resolveCurrentCursor($cursorName = 'cursor', $default = NULL) {}
    /** @return void */
    public static function currentCursorResolver(\Closure $resolver) {}
    /** @return \Illuminate\Contracts\View\Factory */
    public static function viewFactory() {}
    public function getIterator(): \Traversable {}
    /** @return bool */
    public function isEmpty() {}
    /** @return bool */
    public function isNotEmpty() {}
    public function count(): int {}
    /** @return \Illuminate\Support\Collection<TKey, */
    public function getCollection() {}
    /** @return $this */
    public function setCollection(\Illuminate\Support\Collection $collection) {}
    /** @return array */
    public function getOptions() {}
    public function offsetExists($key): bool {}
    public function offsetGet($key): mixed {}
    public function offsetSet($key, $value): void {}
    public function offsetUnset($key): void {}
    /** @return string */
    public function toHtml() {}
    /** @return mixed */
    public function __call($method, $parameters) {}
    public function __toString(): string {}
}
