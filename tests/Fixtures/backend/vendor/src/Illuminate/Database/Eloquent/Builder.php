<?php

namespace Illuminate\Database\Eloquent;

class Builder implements \Illuminate\Contracts\Database\Eloquent\Builder, \Illuminate\Contracts\Database\Query\Builder
{
    use \Illuminate\Database\Concerns\BuildsQueries, \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Database\Eloquent\Concerns\QueriesRelationships;
    protected $query = NULL;
    protected $model = NULL;
    public $pendingAttributes = array (
);
    protected $eagerLoad = array (
);
    protected static $macros = array (
);
    protected $localMacros = array (
);
    protected $onDelete = NULL;
    protected $propertyPassthru = array (
  0 => 'from',
);
    protected $passthru = array (
  0 => 'aggregate',
  1 => 'average',
  2 => 'avg',
  3 => 'count',
  4 => 'dd',
  5 => 'ddrawsql',
  6 => 'doesntexist',
  7 => 'doesntexistor',
  8 => 'dump',
  9 => 'dumprawsql',
  10 => 'exists',
  11 => 'existsor',
  12 => 'explain',
  13 => 'getbindings',
  14 => 'getconnection',
  15 => 'getcountforpagination',
  16 => 'getgrammar',
  17 => 'getrawbindings',
  18 => 'implode',
  19 => 'insert',
  20 => 'insertgetid',
  21 => 'insertorignore',
  22 => 'insertusing',
  23 => 'insertorignoreusing',
  24 => 'max',
  25 => 'min',
  26 => 'numericaggregate',
  27 => 'raw',
  28 => 'rawvalue',
  29 => 'sum',
  30 => 'tosql',
  31 => 'torawsql',
);
    protected $scopes = array (
);
    protected $removedScopes = array (
);
    protected $afterQueryCallbacks = array (
);
    protected $onCloneCallbacks = array (
);
    public function __construct(\Illuminate\Database\Query\Builder $query) {}
    public function make(array $attributes = array (
)) {}
    public function withGlobalScope($identifier, $scope) {}
    public function withoutGlobalScope($scope) {}
    public function withoutGlobalScopes(?array $scopes = NULL) {}
    public function withoutGlobalScopesExcept(array $scopes = array (
)) {}
    public function removedScopes() {}
    public function whereKey($id) {}
    public function whereKeyNot($id) {}
    public function except($models) {}
    public function where($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    public function firstWhere($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    public function orWhere($column, $operator = NULL, $value = NULL) {}
    public function whereNot($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    public function orWhereNot($column, $operator = NULL, $value = NULL) {}
    public function latest($column = NULL) {}
    public function oldest($column = NULL) {}
    public function hydrate(array $items) {}
    public function fillAndInsert(array $values) {}
    public function fillAndInsertOrIgnore(array $values) {}
    public function fillAndInsertGetId(array $values) {}
    public function fillForInsert(array $values) {}
    public function fromQuery($query, $bindings = array (
)) {}
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
    public function findOrNew($id, $columns = array (
  0 => '*',
)) {}
    public function findOr($id, $columns = array (
  0 => '*',
), ?\Closure $callback = NULL) {}
    public function firstOrNew(array $attributes = array (
), array $values = array (
)) {}
    public function firstOrCreate(array $attributes = array (
), \Closure|array $values = array (
)) {}
    public function createOrFirst(array $attributes = array (
), \Closure|array $values = array (
)) {}
    public function updateOrCreate(array $attributes, array $values = array (
)) {}
    public function incrementOrCreate(array $attributes, string $column = 'count', $default = 1, $step = 1, array $extra = array (
)) {}
    public function firstOrFail($columns = array (
  0 => '*',
)) {}
    public function firstOr($columns = array (
  0 => '*',
), ?\Closure $callback = NULL) {}
    public function sole($columns = array (
  0 => '*',
)) {}
    public function value($column) {}
    public function soleValue($column) {}
    public function valueOrFail($column) {}
    public function get($columns = array (
  0 => '*',
)) {}
    public function getModels($columns = array (
  0 => '*',
)) {}
    public function eagerLoadRelations(array $models) {}
    protected function eagerLoadRelation(array $models, $name, \Closure $constraints) {}
    public function getRelation($name) {}
    protected function relationsNestedUnder($relation) {}
    protected function isNestedUnder($relation, $name) {}
    public function afterQuery(\Closure $callback) {}
    public function applyAfterQueryCallbacks($result) {}
    public function cursor() {}
    protected function enforceOrderBy() {}
    public function pluck($column, $key = NULL) {}
    public function paginate($perPage = NULL, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL, $total = NULL) {}
    public function simplePaginate($perPage = NULL, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL) {}
    public function cursorPaginate($perPage = NULL, $columns = array (
  0 => '*',
), $cursorName = 'cursor', $cursor = NULL) {}
    protected function ensureOrderForCursorPagination($shouldReverse = false) {}
    public function create(array $attributes = array (
)) {}
    public function createQuietly(array $attributes = array (
)) {}
    public function forceCreate(array $attributes) {}
    public function forceCreateQuietly(array $attributes = array (
)) {}
    public function update(array $values) {}
    public function upsert(array $values, $uniqueBy, $update = NULL) {}
    public function touch($column = NULL) {}
    public function increment($column, $amount = 1, array $extra = array (
)) {}
    public function decrement($column, $amount = 1, array $extra = array (
)) {}
    protected function addUpdatedAtColumn(array $values) {}
    protected function addUniqueIdsToUpsertValues(array $values) {}
    protected function addTimestampsToUpsertValues(array $values) {}
    protected function addUpdatedAtToUpsertColumns(array $update) {}
    public function delete() {}
    public function forceDelete() {}
    public function onDelete(\Closure $callback) {}
    public function hasNamedScope($scope) {}
    public function scopes($scopes) {}
    public function applyScopes() {}
    protected function callScope(callable $scope, array $parameters = array (
)) {}
    protected function callNamedScope($scope, array $parameters = array (
)) {}
    protected function addNewWheresWithinGroup(\Illuminate\Database\Query\Builder $query, $originalWhereCount) {}
    protected function groupWhereSliceForScope(\Illuminate\Database\Query\Builder $query, $whereSlice) {}
    protected function createNestedWhere($whereSlice, $boolean = 'and') {}
    public function with($relations, $callback = NULL) {}
    public function without($relations) {}
    public function withOnly($relations) {}
    public function newModelInstance($attributes = array (
)) {}
    protected function parseWithRelations(array $relations) {}
    protected function prepareNestedWithRelationships($relations, $prefix = '') {}
    protected function combineConstraints(array $constraints) {}
    protected function parseNameAndAttributeSelectionConstraint($name) {}
    protected function createSelectWithConstraint($name) {}
    protected function addNestedWiths($name, $results) {}
    public function withAttributes(\Illuminate\Contracts\Database\Query\Expression|array|string $attributes, $value = NULL, $asConditions = true) {}
    public function withCasts($casts) {}
    public function withSavepointIfNeeded(\Closure $scope): mixed {}
    protected function getUnionBuilders() {}
    public function getQuery() {}
    public function setQuery($query) {}
    public function toBase() {}
    public function getEagerLoads() {}
    public function setEagerLoads(array $eagerLoad) {}
    public function withoutEagerLoad(array $relations) {}
    public function withoutEagerLoads() {}
    public function getLimit() {}
    public function getOffset() {}
    protected function defaultKeyName() {}
    public function getModel() {}
    public function setModel(\Illuminate\Database\Eloquent\Model $model) {}
    public function qualifyColumn($column) {}
    public function qualifyColumns($columns) {}
    public function getMacro($name) {}
    public function hasMacro($name) {}
    public static function getGlobalMacro($name) {}
    public static function hasGlobalMacro($name) {}
    public function __get($key) {}
    public function __call($method, $parameters) {}
    public static function __callStatic($method, $parameters) {}
    protected static function registerMixin($mixin, $replace) {}
    public function clone() {}
    public function onClone(\Closure $callback) {}
    public function __clone() {}
    public function baseSole($columns = array (
  0 => '*',
)) {}
}
