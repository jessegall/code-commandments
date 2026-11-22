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
    public function url($cursor) {}
    public function previousPageUrl() {}
    public function nextPageUrl() {}
    public function previousCursor() {}
    public function nextCursor() {}
    public function getCursorForItem($item, $isNext = true) {}
    public function getParametersForItem($item) {}
    protected function getPivotParameterForItem($item, $parameterName) {}
    protected function ensureParameterIsPrimitive($parameter) {}
    public function fragment($fragment = NULL) {}
    public function appends($key, $value = NULL) {}
    protected function appendArray(array $keys) {}
    public function withQueryString() {}
    protected function addQuery($key, $value) {}
    protected function buildFragment() {}
    public function loadMorph($relation, $relations) {}
    public function loadMorphCount($relation, $relations) {}
    public function items() {}
    public function through(callable $callback) {}
    public function perPage() {}
    public function cursor() {}
    public function getCursorName() {}
    public function setCursorName($name) {}
    public function withPath($path) {}
    public function setPath($path) {}
    public function path() {}
    public static function resolveCurrentCursor($cursorName = 'cursor', $default = NULL) {}
    public static function currentCursorResolver(\Closure $resolver) {}
    public static function viewFactory() {}
    public function getIterator(): \Traversable {}
    public function isEmpty() {}
    public function isNotEmpty() {}
    public function count(): int {}
    public function getCollection() {}
    public function setCollection(\Illuminate\Support\Collection $collection) {}
    public function getOptions() {}
    public function offsetExists($key): bool {}
    public function offsetGet($key): mixed {}
    public function offsetSet($key, $value): void {}
    public function offsetUnset($key): void {}
    public function toHtml() {}
    public function __call($method, $parameters) {}
    public function __toString(): string {}
}
