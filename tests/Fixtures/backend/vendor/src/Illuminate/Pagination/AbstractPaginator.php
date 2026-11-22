<?php

namespace Illuminate\Pagination;

abstract class AbstractPaginator implements \Illuminate\Contracts\Support\CanBeEscapedWhenCastToString, \Illuminate\Contracts\Support\Htmlable, \Stringable
{
    use \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Support\Traits\Tappable, \Illuminate\Support\Traits\TransformsToResourceCollection;
    protected $items = NULL;
    protected $perPage = NULL;
    protected $currentPage = NULL;
    protected $path = '/';
    protected $query = array (
);
    protected $fragment = NULL;
    protected $pageName = 'page';
    protected $escapeWhenCastingToString = false;
    public $onEachSide = 3;
    protected $options = NULL;
    protected static $currentPathResolver = NULL;
    protected static $currentPageResolver = NULL;
    protected static $queryStringResolver = NULL;
    protected static $viewFactoryResolver = NULL;
    public static $defaultView = 'pagination::tailwind';
    public static $defaultSimpleView = 'pagination::simple-tailwind';
    /** @return bool */
    protected function isValidPageNumber($page) {}
    /** @return string|null */
    public function previousPageUrl() {}
    /** @return array */
    public function getUrlRange($start, $end) {}
    /** @return string */
    public function url($page) {}
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
    /** @return int|null */
    public function firstItem() {}
    /** @return int|null */
    public function lastItem() {}
    /** @return $this */
    public function through(callable $callback) {}
    /** @return int */
    public function perPage() {}
    /** @return bool */
    public function hasPages() {}
    /** @return bool */
    public function onFirstPage() {}
    /** @return bool */
    public function onLastPage() {}
    /** @return int */
    public function currentPage() {}
    /** @return string */
    public function getPageName() {}
    /** @return $this */
    public function setPageName($name) {}
    /** @return $this */
    public function withPath($path) {}
    /** @return $this */
    public function setPath($path) {}
    /** @return $this */
    public function onEachSide($count) {}
    /** @return string|null */
    public function path() {}
    /** @return string */
    public static function resolveCurrentPath($default = '/') {}
    /** @return void */
    public static function currentPathResolver(\Closure $resolver) {}
    /** @return int */
    public static function resolveCurrentPage($pageName = 'page', $default = 1) {}
    /** @return void */
    public static function currentPageResolver(\Closure $resolver) {}
    /** @return string */
    public static function resolveQueryString($default = NULL) {}
    /** @return void */
    public static function queryStringResolver(\Closure $resolver) {}
    /** @return \Illuminate\Contracts\View\Factory */
    public static function viewFactory() {}
    /** @return void */
    public static function viewFactoryResolver(\Closure $resolver) {}
    /** @return void */
    public static function defaultView($view) {}
    /** @return void */
    public static function defaultSimpleView($view) {}
    /** @return void */
    public static function useTailwind() {}
    /** @return void */
    public static function useBootstrap() {}
    /** @return void */
    public static function useBootstrapThree() {}
    /** @return void */
    public static function useBootstrapFour() {}
    /** @return void */
    public static function useBootstrapFive() {}
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
    /** @return $this */
    public function escapeWhenCastingToString($escape = true) {}
}
