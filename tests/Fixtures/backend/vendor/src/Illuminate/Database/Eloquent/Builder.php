<?php

namespace Illuminate\Database\Eloquent;

/**
 * @mixin \Illuminate\Database\Query\Builder
 */
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
    /** @return TModel */
    public function make(array $attributes = array (
)) {}
    /** @return $this */
    public function withGlobalScope($identifier, $scope) {}
    /** @return $this */
    public function withoutGlobalScope($scope) {}
    /** @return $this */
    public function withoutGlobalScopes(?array $scopes = NULL) {}
    /** @return $this */
    public function withoutGlobalScopesExcept(array $scopes = array (
)) {}
    /** @return array */
    public function removedScopes() {}
    /** @return $this */
    public function whereKey($id) {}
    /** @return $this */
    public function whereKeyNot($id) {}
    /** @return static */
    public function except($models) {}
    /** @return $this */
    public function where($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return TModel|null */
    public function firstWhere($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhere($column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function whereNot($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereNot($column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function latest($column = NULL) {}
    /** @return $this */
    public function oldest($column = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function hydrate(array $items) {}
    /** @return bool */
    public function fillAndInsert(array $values) {}
    /** @return int */
    public function fillAndInsertOrIgnore(array $values) {}
    /** @return int */
    public function fillAndInsertGetId(array $values) {}
    /** @return array<int, */
    public function fillForInsert(array $values) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function fromQuery($query, $bindings = array (
)) {}
    /** @return ($id */
    public function find($id, $columns = array (
  0 => '*',
)) {}
    /** @return TModel */
    public function findSole($id, $columns = array (
  0 => '*',
)) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function findMany($ids, $columns = array (
  0 => '*',
)) {}
    /** @return ($id */
    public function findOrFail($id, $columns = array (
  0 => '*',
)) {}
    /** @return ($id */
    public function findOrNew($id, $columns = array (
  0 => '*',
)) {}
    /** @return ( */
    public function findOr($id, $columns = array (
  0 => '*',
), ?\Closure $callback = NULL) {}
    /** @return TModel */
    public function firstOrNew(array $attributes = array (
), array $values = array (
)) {}
    /** @return TModel */
    public function firstOrCreate(array $attributes = array (
), \Closure|array $values = array (
)) {}
    /** @return TModel */
    public function createOrFirst(array $attributes = array (
), \Closure|array $values = array (
)) {}
    /** @return TModel */
    public function updateOrCreate(array $attributes, array $values = array (
)) {}
    /** @return TModel */
    public function incrementOrCreate(array $attributes, string $column = 'count', $default = 1, $step = 1, array $extra = array (
)) {}
    /** @return TModel */
    public function firstOrFail($columns = array (
  0 => '*',
)) {}
    /** @return TModel|TValue */
    public function firstOr($columns = array (
  0 => '*',
), ?\Closure $callback = NULL) {}
    /** @return TModel */
    public function sole($columns = array (
  0 => '*',
)) {}
    /** @return mixed */
    public function value($column) {}
    /** @return mixed */
    public function soleValue($column) {}
    /** @return mixed */
    public function valueOrFail($column) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function get($columns = array (
  0 => '*',
)) {}
    /** @return array<int, */
    public function getModels($columns = array (
  0 => '*',
)) {}
    /** @return array<int, */
    public function eagerLoadRelations(array $models) {}
    /** @return array */
    protected function eagerLoadRelation(array $models, $name, \Closure $constraints) {}
    /** @return \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model, */
    public function getRelation($name) {}
    /** @return array */
    protected function relationsNestedUnder($relation) {}
    /** @return bool */
    protected function isNestedUnder($relation, $name) {}
    /** @return $this */
    public function afterQuery(\Closure $callback) {}
    /** @return mixed */
    public function applyAfterQueryCallbacks($result) {}
    /** @return \Illuminate\Support\LazyCollection<int, */
    public function cursor() {}
    /** @return void */
    protected function enforceOrderBy() {}
    /** @return \Illuminate\Support\Collection<array-key, */
    public function pluck($column, $key = NULL) {}
    /** @return \Illuminate\Pagination\LengthAwarePaginator<int, */
    public function paginate($perPage = NULL, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL, $total = NULL) {}
    /** @return \Illuminate\Pagination\Paginator<int, */
    public function simplePaginate($perPage = NULL, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL) {}
    /** @return \Illuminate\Pagination\CursorPaginator<int, */
    public function cursorPaginate($perPage = NULL, $columns = array (
  0 => '*',
), $cursorName = 'cursor', $cursor = NULL) {}
    /** @return \Illuminate\Support\Collection */
    protected function ensureOrderForCursorPagination($shouldReverse = false) {}
    /** @return TModel */
    public function create(array $attributes = array (
)) {}
    /** @return TModel */
    public function createQuietly(array $attributes = array (
)) {}
    /** @return TModel */
    public function forceCreate(array $attributes) {}
    /** @return TModel */
    public function forceCreateQuietly(array $attributes = array (
)) {}
    /** @return int */
    public function update(array $values) {}
    /** @return int */
    public function upsert(array $values, $uniqueBy, $update = NULL) {}
    /** @return int|false */
    public function touch($column = NULL) {}
    /** @return int */
    public function increment($column, $amount = 1, array $extra = array (
)) {}
    /** @return int */
    public function decrement($column, $amount = 1, array $extra = array (
)) {}
    /** @return array */
    protected function addUpdatedAtColumn(array $values) {}
    /** @return array */
    protected function addUniqueIdsToUpsertValues(array $values) {}
    /** @return array */
    protected function addTimestampsToUpsertValues(array $values) {}
    /** @return array */
    protected function addUpdatedAtToUpsertColumns(array $update) {}
    /** @return mixed */
    public function delete() {}
    /** @return mixed */
    public function forceDelete() {}
    /** @return void */
    public function onDelete(\Closure $callback) {}
    /** @return bool */
    public function hasNamedScope($scope) {}
    /** @return static|mixed */
    public function scopes($scopes) {}
    /** @return static */
    public function applyScopes() {}
    /** @return mixed */
    protected function callScope(callable $scope, array $parameters = array (
)) {}
    /** @return mixed */
    protected function callNamedScope($scope, array $parameters = array (
)) {}
    /** @return void */
    protected function addNewWheresWithinGroup(\Illuminate\Database\Query\Builder $query, $originalWhereCount) {}
    /** @return void */
    protected function groupWhereSliceForScope(\Illuminate\Database\Query\Builder $query, $whereSlice) {}
    /** @return array */
    protected function createNestedWhere($whereSlice, $boolean = 'and') {}
    /** @return $this */
    public function with($relations, $callback = NULL) {}
    /** @return $this */
    public function without($relations) {}
    /** @return $this */
    public function withOnly($relations) {}
    /** @return TModel */
    public function newModelInstance($attributes = array (
)) {}
    /** @return array */
    protected function parseWithRelations(array $relations) {}
    /** @return array */
    protected function prepareNestedWithRelationships($relations, $prefix = '') {}
    /** @return \Closure */
    protected function combineConstraints(array $constraints) {}
    /** @return array */
    protected function parseNameAndAttributeSelectionConstraint($name) {}
    /** @return array */
    protected function createSelectWithConstraint($name) {}
    /** @return array */
    protected function addNestedWiths($name, $results) {}
    /** @return $this */
    public function withAttributes(\Illuminate\Contracts\Database\Query\Expression|array|string $attributes, $value = NULL, $asConditions = true) {}
    /** @return $this */
    public function withCasts($casts) {}
    public function withSavepointIfNeeded(\Closure $scope): mixed {}
    /** @return \Illuminate\Support\Collection */
    protected function getUnionBuilders() {}
    /** @return \Illuminate\Database\Query\Builder */
    public function getQuery() {}
    /** @return $this */
    public function setQuery($query) {}
    /** @return \Illuminate\Database\Query\Builder */
    public function toBase() {}
    /** @return array */
    public function getEagerLoads() {}
    /** @return $this */
    public function setEagerLoads(array $eagerLoad) {}
    /** @return $this */
    public function withoutEagerLoad(array $relations) {}
    /** @return $this */
    public function withoutEagerLoads() {}
    /** @return mixed */
    public function getLimit() {}
    /** @return mixed */
    public function getOffset() {}
    /** @return string */
    protected function defaultKeyName() {}
    /** @return TModel */
    public function getModel() {}
    /** @return static<TModelNew> */
    public function setModel(\Illuminate\Database\Eloquent\Model $model) {}
    /** @return string */
    public function qualifyColumn($column) {}
    /** @return array */
    public function qualifyColumns($columns) {}
    /** @return \Closure */
    public function getMacro($name) {}
    /** @return bool */
    public function hasMacro($name) {}
    /** @return \Closure */
    public static function getGlobalMacro($name) {}
    /** @return bool */
    public static function hasGlobalMacro($name) {}
    /** @return mixed */
    public function __get($key) {}
    /** @return mixed */
    public function __call($method, $parameters) {}
    /** @return mixed */
    public static function __callStatic($method, $parameters) {}
    /** @return void */
    protected static function registerMixin($mixin, $replace) {}
    /** @return static */
    public function clone() {}
    /** @return $this */
    public function onClone(\Closure $callback) {}
    /** @return void */
    public function __clone() {}
    /** @return TValue */
    public function baseSole($columns = array (
  0 => '*',
)) {}
}
