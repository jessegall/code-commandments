<?php

namespace Illuminate\Database\Concerns;

trait BuildsQueries
{
    use \Illuminate\Support\Traits\Conditionable;
    public function chunk($count, callable $callback) {}
    public function chunkMap(callable $callback, $count = 1000) {}
    public function each(callable $callback, $count = 1000) {}
    public function chunkById($count, callable $callback, $column = NULL, $alias = NULL) {}
    public function chunkByIdDesc($count, callable $callback, $column = NULL, $alias = NULL) {}
    public function orderedChunkById($count, callable $callback, $column = NULL, $alias = NULL, $descending = false) {}
    public function eachById(callable $callback, $count = 1000, $column = NULL, $alias = NULL) {}
    public function lazy($chunkSize = 1000) {}
    public function lazyById($chunkSize = 1000, $column = NULL, $alias = NULL) {}
    public function lazyByIdDesc($chunkSize = 1000, $column = NULL, $alias = NULL) {}
    protected function orderedLazyById($chunkSize = 1000, $column = NULL, $alias = NULL, $descending = false) {}
    public function first($columns = array (
  0 => '*',
)) {}
    public function firstOrFail($columns = array (
  0 => '*',
), $message = NULL) {}
    public function sole($columns = array (
  0 => '*',
)) {}
    protected function paginateUsingCursor($perPage, $columns = array (
  0 => '*',
), $cursorName = 'cursor', $cursor = NULL) {}
    protected function getOriginalColumnNameForCursorPagination($builder, string $parameter) {}
    protected function paginator($items, $total, $perPage, $currentPage, $options) {}
    protected function simplePaginator($items, $perPage, $currentPage, $options) {}
    protected function cursorPaginator($items, $perPage, $cursor, $options) {}
    public function tap($callback) {}
    public function pipe($callback) {}
    public function when($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
    public function unless($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
}
