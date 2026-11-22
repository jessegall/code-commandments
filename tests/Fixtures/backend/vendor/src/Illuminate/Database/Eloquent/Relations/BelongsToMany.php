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
    protected function resolveTableName($table) {}
    public function addConstraints() {}
    protected function performJoin($query = NULL) {}
    protected function addWhereConstraints() {}
    public function addEagerConstraints(array $models) {}
    public function initRelation(array $models, $relation) {}
    public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
    protected function buildDictionary(\Illuminate\Database\Eloquent\Collection $results) {}
    public function getPivotClass() {}
    public function using($class) {}
    public function as($accessor) {}
    public function wherePivot($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    public function wherePivotBetween($column, array $values, $boolean = 'and', $not = false) {}
    public function orWherePivotBetween($column, array $values) {}
    public function wherePivotNotBetween($column, array $values, $boolean = 'and') {}
    public function orWherePivotNotBetween($column, array $values) {}
    public function wherePivotIn($column, $values, $boolean = 'and', $not = false) {}
    public function orWherePivot($column, $operator = NULL, $value = NULL) {}
    public function withPivotValue($column, $value = NULL) {}
    public function orWherePivotIn($column, $values) {}
    public function wherePivotNotIn($column, $values, $boolean = 'and') {}
    public function orWherePivotNotIn($column, $values) {}
    public function wherePivotNull($column, $boolean = 'and', $not = false) {}
    public function wherePivotNotNull($column, $boolean = 'and') {}
    public function orWherePivotNull($column, $not = false) {}
    public function orWherePivotNotNull($column) {}
    public function orderByPivot($column, $direction = 'asc') {}
    public function orderByPivotDesc($column) {}
    public function findOrNew($id, $columns = array (
  0 => '*',
)) {}
    public function firstOrNew(array $attributes = array (
), array $values = array (
)) {}
    public function firstOrCreate(array $attributes = array (
), \Closure|array $values = array (
), array $joining = array (
), $touch = true) {}
    public function createOrFirst(array $attributes = array (
), \Closure|array $values = array (
), array $joining = array (
), $touch = true) {}
    public function updateOrCreate(array $attributes, array $values = array (
), array $joining = array (
), $touch = true) {}
    public function find($id, $columns = array (
  0 => '*',
)) {}
    public function findSole($id, $columns = array (
  0 => '*',
)) {}
    public function findMany($ids, $columns = array (
  0 => '*',
)) {}
    public function findOrFail($id, $columns = array (
  0 => '*',
)) {}
    public function findOr($id, $columns = array (
  0 => '*',
), ?\Closure $callback = NULL) {}
    public function firstWhere($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    public function first($columns = array (
  0 => '*',
)) {}
    public function firstOrFail($columns = array (
  0 => '*',
)) {}
    public function firstOr($columns = array (
  0 => '*',
), ?\Closure $callback = NULL) {}
    public function getResults() {}
    public function get($columns = array (
  0 => '*',
)) {}
    protected function shouldSelect(array $columns = array (
  0 => '*',
)) {}
    protected function aliasedPivotColumns() {}
    public function paginate($perPage = NULL, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL) {}
    public function simplePaginate($perPage = NULL, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL) {}
    public function cursorPaginate($perPage = NULL, $columns = array (
  0 => '*',
), $cursorName = 'cursor', $cursor = NULL) {}
    public function chunk($count, callable $callback) {}
    public function chunkById($count, callable $callback, $column = NULL, $alias = NULL) {}
    public function chunkByIdDesc($count, callable $callback, $column = NULL, $alias = NULL) {}
    public function eachById(callable $callback, $count = 1000, $column = NULL, $alias = NULL) {}
    public function orderedChunkById($count, callable $callback, $column = NULL, $alias = NULL, $descending = false) {}
    public function each(callable $callback, $count = 1000) {}
    public function lazy($chunkSize = 1000) {}
    public function lazyById($chunkSize = 1000, $column = NULL, $alias = NULL) {}
    public function lazyByIdDesc($chunkSize = 1000, $column = NULL, $alias = NULL) {}
    public function cursor() {}
    protected function prepareQueryBuilder() {}
    protected function hydratePivotRelation(array $models) {}
    protected function migratePivotAttributes(\Illuminate\Database\Eloquent\Model $model) {}
    public function touchIfTouching() {}
    protected function touchingParent() {}
    protected function guessInverseRelation() {}
    public function touch() {}
    public function allRelatedIds() {}
    public function save(\Illuminate\Database\Eloquent\Model $model, array $pivotAttributes = array (
), $touch = true) {}
    public function saveQuietly(\Illuminate\Database\Eloquent\Model $model, array $pivotAttributes = array (
), $touch = true) {}
    public function saveMany($models, array $pivotAttributes = array (
)) {}
    public function saveManyQuietly($models, array $pivotAttributes = array (
)) {}
    public function create(array $attributes = array (
), array $joining = array (
), $touch = true) {}
    public function createMany(iterable $records, array $joinings = array (
)) {}
    public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    public function getRelationExistenceQueryForSelfJoin(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    public function take($value) {}
    public function limit($value) {}
    public function getExistenceCompareKey() {}
    public function withTimestamps($createdAt = NULL, $updatedAt = NULL) {}
    public function createdAt() {}
    public function updatedAt() {}
    public function getForeignPivotKeyName() {}
    public function getQualifiedForeignPivotKeyName() {}
    public function getRelatedPivotKeyName() {}
    public function getQualifiedRelatedPivotKeyName() {}
    public function getParentKeyName() {}
    public function getQualifiedParentKeyName() {}
    public function getRelatedKeyName() {}
    public function getQualifiedRelatedKeyName() {}
    public function getTable() {}
    public function getRelationName() {}
    public function getPivotAccessor() {}
    public function getPivotColumns() {}
    public function qualifyPivotColumn($column) {}
}
