<?php

namespace Illuminate\Database\Eloquent\Relations;

class BelongsToMany extends \Illuminate\Database\Eloquent\Relations\Relation
{
    use \Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithDictionary, \Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithPivotTable;
    protected $table = NULL;
    protected $foreignPivotKey = NULL;
    protected $relatedPivotKey = NULL;
    protected $parentKey = NULL;
    protected $relatedKey = NULL;
    protected $relationName = NULL;
    protected $pivotColumns = array (
);
    protected $pivotWheres = array (
);
    protected $pivotWhereIns = array (
);
    protected $pivotWhereNulls = array (
);
    protected $pivotValues = array (
);
    public $withTimestamps = false;
    protected $pivotCreatedAt = NULL;
    protected $pivotUpdatedAt = NULL;
    protected $using = NULL;
    protected $accessor = 'pivot';
    public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = NULL) {}
    /** @return string */
    protected function resolveTableName($table) {}
    /** @return void */
    public function addConstraints() {}
    /** @return $this */
    protected function performJoin($query = NULL) {}
    /** @return $this */
    protected function addWhereConstraints() {}
    public function addEagerConstraints(array $models) {}
    public function initRelation(array $models, $relation) {}
    public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
    /** @return array<array<array-key, */
    protected function buildDictionary(\Illuminate\Database\Eloquent\Collection $results) {}
    /** @return class-string<TPivotModel> */
    public function getPivotClass() {}
    /** @return $this */
    public function using($class) {}
    /** @return $this */
    public function as($accessor) {}
    /** @return $this */
    public function wherePivot($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function wherePivotBetween($column, array $values, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWherePivotBetween($column, array $values) {}
    /** @return $this */
    public function wherePivotNotBetween($column, array $values, $boolean = 'and') {}
    /** @return $this */
    public function orWherePivotNotBetween($column, array $values) {}
    /** @return $this */
    public function wherePivotIn($column, $values, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWherePivot($column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function withPivotValue($column, $value = NULL) {}
    /** @return $this */
    public function orWherePivotIn($column, $values) {}
    /** @return $this */
    public function wherePivotNotIn($column, $values, $boolean = 'and') {}
    /** @return $this */
    public function orWherePivotNotIn($column, $values) {}
    /** @return $this */
    public function wherePivotNull($column, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function wherePivotNotNull($column, $boolean = 'and') {}
    /** @return $this */
    public function orWherePivotNull($column, $not = false) {}
    /** @return $this */
    public function orWherePivotNotNull($column) {}
    /** @return $this */
    public function orderByPivot($column, $direction = 'asc') {}
    /** @return $this */
    public function orderByPivotDesc($column) {}
    /** @return ( */
    public function findOrNew($id, $columns = array (
  0 => '*',
)) {}
    /** @return TRelatedModel&object{pivot: */
    public function firstOrNew(array $attributes = array (
), array $values = array (
)) {}
    /** @return TRelatedModel&object{pivot: */
    public function firstOrCreate(array $attributes = array (
), \Closure|array $values = array (
), array $joining = array (
), $touch = true) {}
    /** @return TRelatedModel&object{pivot: */
    public function createOrFirst(array $attributes = array (
), \Closure|array $values = array (
), array $joining = array (
), $touch = true) {}
    /** @return TRelatedModel&object{pivot: */
    public function updateOrCreate(array $attributes, array $values = array (
), array $joining = array (
), $touch = true) {}
    /** @return ( */
    public function find($id, $columns = array (
  0 => '*',
)) {}
    /** @return TRelatedModel&object{pivot: */
    public function findSole($id, $columns = array (
  0 => '*',
)) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function findMany($ids, $columns = array (
  0 => '*',
)) {}
    /** @return ( */
    public function findOrFail($id, $columns = array (
  0 => '*',
)) {}
    /** @return ( */
    public function findOr($id, $columns = array (
  0 => '*',
), ?\Closure $callback = NULL) {}
    /** @return (TRelatedModel&object{pivot: */
    public function firstWhere($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return (TRelatedModel&object{pivot: */
    public function first($columns = array (
  0 => '*',
)) {}
    /** @return TRelatedModel&object{pivot: */
    public function firstOrFail($columns = array (
  0 => '*',
)) {}
    /** @return (TRelatedModel&object{pivot: */
    public function firstOr($columns = array (
  0 => '*',
), ?\Closure $callback = NULL) {}
    public function getResults() {}
    public function get($columns = array (
  0 => '*',
)) {}
    /** @return array */
    protected function shouldSelect(array $columns = array (
  0 => '*',
)) {}
    /** @return array */
    protected function aliasedPivotColumns() {}
    /** @return \Illuminate\Pagination\LengthAwarePaginator<int, */
    public function paginate($perPage = NULL, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL) {}
    /** @return \Illuminate\Contracts\Pagination\Paginator<int, */
    public function simplePaginate($perPage = NULL, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL) {}
    /** @return \Illuminate\Contracts\Pagination\CursorPaginator<int, */
    public function cursorPaginate($perPage = NULL, $columns = array (
  0 => '*',
), $cursorName = 'cursor', $cursor = NULL) {}
    /** @return bool */
    public function chunk($count, callable $callback) {}
    /** @return bool */
    public function chunkById($count, callable $callback, $column = NULL, $alias = NULL) {}
    /** @return bool */
    public function chunkByIdDesc($count, callable $callback, $column = NULL, $alias = NULL) {}
    /** @return bool */
    public function eachById(callable $callback, $count = 1000, $column = NULL, $alias = NULL) {}
    /** @return bool */
    public function orderedChunkById($count, callable $callback, $column = NULL, $alias = NULL, $descending = false) {}
    /** @return bool */
    public function each(callable $callback, $count = 1000) {}
    /** @return \Illuminate\Support\LazyCollection<int, */
    public function lazy($chunkSize = 1000) {}
    /** @return \Illuminate\Support\LazyCollection<int, */
    public function lazyById($chunkSize = 1000, $column = NULL, $alias = NULL) {}
    /** @return \Illuminate\Support\LazyCollection<int, */
    public function lazyByIdDesc($chunkSize = 1000, $column = NULL, $alias = NULL) {}
    /** @return \Illuminate\Support\LazyCollection<int, */
    public function cursor() {}
    /** @return \Illuminate\Database\Eloquent\Builder<TRelatedModel> */
    protected function prepareQueryBuilder() {}
    /** @return void */
    protected function hydratePivotRelation(array $models) {}
    /** @return array */
    protected function migratePivotAttributes(\Illuminate\Database\Eloquent\Model $model) {}
    /** @return void */
    public function touchIfTouching() {}
    /** @return bool */
    protected function touchingParent() {}
    /** @return string */
    protected function guessInverseRelation() {}
    /** @return void */
    public function touch() {}
    /** @return \Illuminate\Support\Collection<int, */
    public function allRelatedIds() {}
    /** @return TRelatedModel&object{pivot: */
    public function save(\Illuminate\Database\Eloquent\Model $model, array $pivotAttributes = array (
), $touch = true) {}
    /** @return TRelatedModel&object{pivot: */
    public function saveQuietly(\Illuminate\Database\Eloquent\Model $model, array $pivotAttributes = array (
), $touch = true) {}
    /** @return TContainer */
    public function saveMany($models, array $pivotAttributes = array (
)) {}
    /** @return TContainer */
    public function saveManyQuietly($models, array $pivotAttributes = array (
)) {}
    /** @return TRelatedModel&object{pivot: */
    public function create(array $attributes = array (
), array $joining = array (
), $touch = true) {}
    /** @return array<int, */
    public function createMany(iterable $records, array $joinings = array (
)) {}
    public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    /** @return \Illuminate\Database\Eloquent\Builder<TRelatedModel> */
    public function getRelationExistenceQueryForSelfJoin(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    /** @return $this */
    public function take($value) {}
    /** @return $this */
    public function limit($value) {}
    /** @return string */
    public function getExistenceCompareKey() {}
    /** @return $this */
    public function withTimestamps($createdAt = NULL, $updatedAt = NULL) {}
    /** @return string */
    public function createdAt() {}
    /** @return string */
    public function updatedAt() {}
    /** @return string */
    public function getForeignPivotKeyName() {}
    /** @return string */
    public function getQualifiedForeignPivotKeyName() {}
    /** @return string */
    public function getRelatedPivotKeyName() {}
    /** @return string */
    public function getQualifiedRelatedPivotKeyName() {}
    /** @return string */
    public function getParentKeyName() {}
    /** @return string */
    public function getQualifiedParentKeyName() {}
    /** @return string */
    public function getRelatedKeyName() {}
    /** @return string */
    public function getQualifiedRelatedKeyName() {}
    /** @return string */
    public function getTable() {}
    /** @return string */
    public function getRelationName() {}
    /** @return TAccessor */
    public function getPivotAccessor() {}
    /** @return array */
    public function getPivotColumns() {}
    /** @return string|\Illuminate\Contracts\Database\Query\Expression */
    public function qualifyPivotColumn($column) {}
}
