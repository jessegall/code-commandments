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
    protected function isValidPageNumber($page) {}
    public function previousPageUrl() {}
    public function getUrlRange($start, $end) {}
    public function url($page) {}
    public function fragment($fragment = NULL) {}
    public function appends($key, $value = NULL) {}
    protected function appendArray(array $keys) {}
    public function withQueryString() {}
    protected function addQuery($key, $value) {}
    protected function buildFragment() {}
    public function loadMorph($relation, $relations) {}
    public function loadMorphCount($relation, $relations) {}
    public function items() {}
    public function firstItem() {}
    public function lastItem() {}
    public function through(callable $callback) {}
    public function perPage() {}
    public function hasPages() {}
    public function onFirstPage() {}
    public function onLastPage() {}
    public function currentPage() {}
    public function getPageName() {}
    public function setPageName($name) {}
    public function withPath($path) {}
    public function setPath($path) {}
    public function onEachSide($count) {}
    public function path() {}
    public static function resolveCurrentPath($default = '/') {}
    public static function currentPathResolver(\Closure $resolver) {}
    public static function resolveCurrentPage($pageName = 'page', $default = 1) {}
    public static function currentPageResolver(\Closure $resolver) {}
    public static function resolveQueryString($default = NULL) {}
    public static function queryStringResolver(\Closure $resolver) {}
    public static function viewFactory() {}
    public static function viewFactoryResolver(\Closure $resolver) {}
    public static function defaultView($view) {}
    public static function defaultSimpleView($view) {}
    public static function useTailwind() {}
    public static function useBootstrap() {}
    public static function useBootstrapThree() {}
    public static function useBootstrapFour() {}
    public static function useBootstrapFive() {}
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
    public function escapeWhenCastingToString($escape = true) {}
}
