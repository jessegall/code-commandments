<?php

namespace Illuminate\Database\Concerns;

trait BuildsQueries
{
    use \Illuminate\Support\Traits\Conditionable;
    /** @return bool */
    public function chunk($count, callable $callback) {}
    /** @return \Illuminate\Support\Collection<int, */
    public function chunkMap(callable $callback, $count = 1000) {}
    /** @return bool */
    public function each(callable $callback, $count = 1000) {}
    /** @return bool */
    public function chunkById($count, callable $callback, $column = NULL, $alias = NULL) {}
    /** @return bool */
    public function chunkByIdDesc($count, callable $callback, $column = NULL, $alias = NULL) {}
    /** @return bool */
    public function orderedChunkById($count, callable $callback, $column = NULL, $alias = NULL, $descending = false) {}
    /** @return bool */
    public function eachById(callable $callback, $count = 1000, $column = NULL, $alias = NULL) {}
    /** @return \Illuminate\Support\LazyCollection<int, */
    public function lazy($chunkSize = 1000) {}
    /** @return \Illuminate\Support\LazyCollection<int, */
    public function lazyById($chunkSize = 1000, $column = NULL, $alias = NULL) {}
    /** @return \Illuminate\Support\LazyCollection<int, */
    public function lazyByIdDesc($chunkSize = 1000, $column = NULL, $alias = NULL) {}
    /** @return \Illuminate\Support\LazyCollection */
    protected function orderedLazyById($chunkSize = 1000, $column = NULL, $alias = NULL, $descending = false) {}
    /** @return TValue|null */
    public function first($columns = array (
  0 => '*',
)) {}
    /** @return TValue */
    public function firstOrFail($columns = array (
  0 => '*',
), $message = NULL) {}
    /** @return TValue */
    public function sole($columns = array (
  0 => '*',
)) {}
    /** @return \Illuminate\Contracts\Pagination\CursorPaginator */
    protected function paginateUsingCursor($perPage, $columns = array (
  0 => '*',
), $cursorName = 'cursor', $cursor = NULL) {}
    /** @return string */
    protected function getOriginalColumnNameForCursorPagination($builder, string $parameter) {}
    /** @return \Illuminate\Pagination\LengthAwarePaginator */
    protected function paginator($items, $total, $perPage, $currentPage, $options) {}
    /** @return \Illuminate\Pagination\Paginator */
    protected function simplePaginator($items, $perPage, $currentPage, $options) {}
    /** @return \Illuminate\Pagination\CursorPaginator */
    protected function cursorPaginator($items, $perPage, $cursor, $options) {}
    /** @return $this */
    public function tap($callback) {}
    /** @return (TReturn */
    public function pipe($callback) {}
    /** @return $this|TWhenReturnType */
    public function when($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
    /** @return $this|TUnlessReturnType */
    public function unless($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
}
