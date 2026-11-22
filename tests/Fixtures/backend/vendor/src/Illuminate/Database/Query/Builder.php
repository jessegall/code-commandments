<?php

namespace Illuminate\Database\Query;

class Builder implements \Illuminate\Contracts\Database\Query\Builder
{
    use \Illuminate\Database\Concerns\BuildsWhereDateClauses, \Illuminate\Database\Concerns\BuildsQueries, \Illuminate\Database\Concerns\ExplainsQueries, \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Support\Traits\Macroable;
    public $connection = NULL;
    public $grammar = NULL;
    public $processor = NULL;
    public $bindings;
    public $aggregate = NULL;
    public $columns = NULL;
    public $distinct = false;
    public $from = NULL;
    public $indexHint = NULL;
    public $joins = NULL;
    public $wheres = array (
);
    public $groups = NULL;
    public $havings = NULL;
    public $orders = NULL;
    public $limit = NULL;
    public $groupLimit = NULL;
    public $offset = NULL;
    public $unions = NULL;
    public $unionLimit = NULL;
    public $unionOffset = NULL;
    public $unionOrders = NULL;
    public $lock = NULL;
    public $timeout = NULL;
    public $beforeQueryCallbacks = array (
);
    protected $afterQueryCallbacks = array (
);
    public $operators = array (
  0 => '=',
  1 => '<',
  2 => '>',
  3 => '<=',
  4 => '>=',
  5 => '<>',
  6 => '!=',
  7 => '<=>',
  8 => 'like',
  9 => 'like binary',
  10 => 'not like',
  11 => 'ilike',
  12 => '&',
  13 => '|',
  14 => '^',
  15 => '<<',
  16 => '>>',
  17 => '&~',
  18 => 'is',
  19 => 'is not',
  20 => 'rlike',
  21 => 'not rlike',
  22 => 'regexp',
  23 => 'not regexp',
  24 => '~',
  25 => '~*',
  26 => '!~',
  27 => '!~*',
  28 => 'similar to',
  29 => 'not similar to',
  30 => 'not ilike',
  31 => '~~*',
  32 => '!~~*',
);
    public $bitwiseOperators = array (
  0 => '&',
  1 => '|',
  2 => '^',
  3 => '<<',
  4 => '>>',
  5 => '&~',
);
    public $useWritePdo = false;
    public function __construct(\Illuminate\Database\ConnectionInterface $connection, ?\Illuminate\Database\Query\Grammars\Grammar $grammar = NULL, ?\Illuminate\Database\Query\Processors\Processor $processor = NULL) {}
    /** @return $this */
    public function select($columns = array (
  0 => '*',
)) {}
    /** @return $this */
    public function selectSub($query, $as) {}
    /** @return $this */
    public function selectExpression($expression, $as) {}
    /** @return $this */
    public function selectRaw($expression, array $bindings = array (
)) {}
    /** @return $this */
    public function fromSub($query, $as) {}
    /** @return $this */
    public function fromRaw($expression, $bindings = array (
)) {}
    /** @return array */
    protected function createSub($query) {}
    /** @return array */
    protected function parseSub($query) {}
    /** @return mixed */
    protected function prependDatabaseNameIfCrossDatabaseQuery($query) {}
    /** @return $this */
    public function addSelect($column) {}
    /** @return $this */
    public function selectVectorDistance($column, $vector, $as = NULL) {}
    /** @return $this */
    public function distinct() {}
    /** @return $this */
    public function from($table, $as = NULL) {}
    /** @return $this */
    public function useIndex($index) {}
    /** @return $this */
    public function forceIndex($index) {}
    /** @return $this */
    public function ignoreIndex($index) {}
    /** @return $this */
    public function join($table, $first, $operator = NULL, $second = NULL, $type = 'inner', $where = false) {}
    /** @return $this */
    public function joinWhere($table, $first, $operator, $second, $type = 'inner') {}
    /** @return $this */
    public function joinSub($query, $as, $first, $operator = NULL, $second = NULL, $type = 'inner', $where = false) {}
    /** @return $this */
    public function joinLateral($query, string $as, string $type = 'inner') {}
    /** @return $this */
    public function leftJoinLateral($query, string $as) {}
    /** @return $this */
    public function leftJoin($table, $first, $operator = NULL, $second = NULL) {}
    /** @return $this */
    public function leftJoinWhere($table, $first, $operator, $second) {}
    /** @return $this */
    public function leftJoinSub($query, $as, $first, $operator = NULL, $second = NULL) {}
    /** @return $this */
    public function rightJoin($table, $first, $operator = NULL, $second = NULL) {}
    /** @return $this */
    public function rightJoinWhere($table, $first, $operator, $second) {}
    /** @return $this */
    public function rightJoinSub($query, $as, $first, $operator = NULL, $second = NULL) {}
    /** @return $this */
    public function crossJoin($table, $first = NULL, $operator = NULL, $second = NULL) {}
    /** @return $this */
    public function crossJoinSub($query, $as) {}
    /** @return \Illuminate\Database\Query\JoinClause */
    protected function newJoinClause(\Illuminate\Database\Query\Builder $parentQuery, $type, $table) {}
    /** @return \Illuminate\Database\Query\JoinLateralClause */
    protected function newJoinLateralClause(\Illuminate\Database\Query\Builder $parentQuery, $type, $table) {}
    /** @return $this */
    public function mergeWheres($wheres, $bindings) {}
    /** @return $this */
    public function where($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    protected function addArrayOfWheres($column, $boolean, $method = 'where') {}
    /** @return array */
    public function prepareValueAndOperator($value, $operator, $useDefault = false) {}
    /** @return bool */
    protected function invalidOperatorAndValue($operator, $value) {}
    /** @return bool */
    protected function invalidOperator($operator) {}
    /** @return bool */
    protected function isBitwiseOperator($operator) {}
    /** @return $this */
    public function orWhere($column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function whereNot($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereNot($column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function whereColumn($first, $operator = NULL, $second = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereColumn($first, $operator = NULL, $second = NULL) {}
    /** @return $this */
    public function whereVectorSimilarTo($column, $vector, $minSimilarity = 0.6, $order = true) {}
    /** @return $this */
    public function whereVectorDistanceLessThan($column, $vector, $maxDistance, $boolean = 'and') {}
    /** @return $this */
    public function orWhereVectorDistanceLessThan($column, $vector, $maxDistance) {}
    /** @return $this */
    public function whereRaw($sql, $bindings = array (
), $boolean = 'and') {}
    /** @return $this */
    public function orWhereRaw($sql, $bindings = array (
)) {}
    /** @return $this */
    public function whereLike($column, $value, $caseSensitive = false, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWhereLike($column, $value, $caseSensitive = false) {}
    /** @return $this */
    public function whereNotLike($column, $value, $caseSensitive = false, $boolean = 'and') {}
    /** @return $this */
    public function orWhereNotLike($column, $value, $caseSensitive = false) {}
    /** @return $this */
    public function whereNullSafeEquals($column, $value, $boolean = 'and') {}
    /** @return $this */
    public function orWhereNullSafeEquals($column, $value) {}
    /** @return $this */
    public function whereIn($column, $values, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWhereIn($column, $values) {}
    /** @return $this */
    public function whereNotIn($column, $values, $boolean = 'and') {}
    /** @return $this */
    public function orWhereNotIn($column, $values) {}
    /** @return $this */
    public function whereIntegerInRaw($column, $values, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWhereIntegerInRaw($column, $values) {}
    /** @return $this */
    public function whereIntegerNotInRaw($column, $values, $boolean = 'and') {}
    /** @return $this */
    public function orWhereIntegerNotInRaw($column, $values) {}
    /** @return $this */
    public function whereNull($columns, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWhereNull($column) {}
    /** @return $this */
    public function whereNotNull($columns, $boolean = 'and') {}
    /** @return $this */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function whereBetweenColumns($column, array $values, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWhereBetween($column, iterable $values) {}
    /** @return $this */
    public function orWhereBetweenColumns($column, array $values) {}
    /** @return $this */
    public function whereNotBetween($column, iterable $values, $boolean = 'and') {}
    /** @return $this */
    public function whereNotBetweenColumns($column, array $values, $boolean = 'and') {}
    /** @return $this */
    public function orWhereNotBetween($column, iterable $values) {}
    /** @return $this */
    public function orWhereNotBetweenColumns($column, array $values) {}
    /** @return $this */
    public function whereValueBetween($value, array $columns, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWhereValueBetween($value, array $columns) {}
    /** @return $this */
    public function whereValueNotBetween($value, array $columns, $boolean = 'and') {}
    /** @return $this */
    public function orWhereValueNotBetween($value, array $columns) {}
    /** @return $this */
    public function orWhereNotNull($column) {}
    /** @return $this */
    public function whereDate($column, $operator, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereDate($column, $operator, $value = NULL) {}
    /** @return $this */
    public function whereTime($column, $operator, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereTime($column, $operator, $value = NULL) {}
    /** @return $this */
    public function whereDay($column, $operator, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereDay($column, $operator, $value = NULL) {}
    /** @return $this */
    public function whereMonth($column, $operator, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereMonth($column, $operator, $value = NULL) {}
    /** @return $this */
    public function whereYear($column, $operator, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereYear($column, $operator, $value = NULL) {}
    /** @return $this */
    protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and') {}
    /** @return $this */
    public function whereNested(\Closure $callback, $boolean = 'and') {}
    /** @return \Illuminate\Database\Query\Builder */
    public function forNestedWhere() {}
    /** @return $this */
    public function addNestedWhereQuery($query, $boolean = 'and') {}
    /** @return $this */
    protected function whereSub($column, $operator, $callback, $boolean) {}
    /** @return $this */
    public function whereExists($callback, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWhereExists($callback, $not = false) {}
    /** @return $this */
    public function whereNotExists($callback, $boolean = 'and') {}
    /** @return $this */
    public function orWhereNotExists($callback) {}
    /** @return $this */
    public function addWhereExistsQuery(\Illuminate\Database\Query\Builder $query, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function whereRowValues($columns, $operator, $values, $boolean = 'and') {}
    /** @return $this */
    public function orWhereRowValues($columns, $operator, $values) {}
    /** @return $this */
    public function whereJsonContains($column, $value, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWhereJsonContains($column, $value) {}
    /** @return $this */
    public function whereJsonDoesntContain($column, $value, $boolean = 'and') {}
    /** @return $this */
    public function orWhereJsonDoesntContain($column, $value) {}
    /** @return $this */
    public function whereJsonOverlaps($column, $value, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWhereJsonOverlaps($column, $value) {}
    /** @return $this */
    public function whereJsonDoesntOverlap($column, $value, $boolean = 'and') {}
    /** @return $this */
    public function orWhereJsonDoesntOverlap($column, $value) {}
    /** @return $this */
    public function whereJsonContainsKey($column, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orWhereJsonContainsKey($column) {}
    /** @return $this */
    public function whereJsonDoesntContainKey($column, $boolean = 'and') {}
    /** @return $this */
    public function orWhereJsonDoesntContainKey($column) {}
    /** @return $this */
    public function whereJsonLength($column, $operator, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereJsonLength($column, $operator, $value = NULL) {}
    /** @return $this */
    public function dynamicWhere($method, $parameters) {}
    /** @return void */
    protected function addDynamic($segment, $connector, $parameters, $index) {}
    /** @return $this */
    public function whereFullText($columns, $value, array $options = array (
), $boolean = 'and') {}
    /** @return $this */
    public function orWhereFullText($columns, $value, array $options = array (
)) {}
    /** @return $this */
    public function whereAll($columns, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereAll($columns, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function whereAny($columns, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereAny($columns, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function whereNone($columns, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereNone($columns, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function groupBy(...$groups) {}
    /** @return $this */
    public function groupByRaw($sql, array $bindings = array (
)) {}
    /** @return $this */
    public function having($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orHaving($column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function havingNested(\Closure $callback, $boolean = 'and') {}
    /** @return $this */
    public function addNestedHavingQuery($query, $boolean = 'and') {}
    /** @return $this */
    public function havingNull($columns, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function orHavingNull($column) {}
    /** @return $this */
    public function havingNotNull($columns, $boolean = 'and') {}
    /** @return $this */
    public function orHavingNotNull($column) {}
    /** @return $this */
    public function havingBetween($column, iterable $values, $boolean = 'and', $not = false) {}
    /** @return $this */
    public function havingNotBetween($column, iterable $values, $boolean = 'and') {}
    /** @return $this */
    public function orHavingBetween($column, iterable $values) {}
    /** @return $this */
    public function orHavingNotBetween($column, iterable $values) {}
    /** @return array{\DateTimeInterface, */
    protected function resolveDatePeriodBounds(\DatePeriod $period) {}
    /** @return $this */
    public function havingRaw($sql, array $bindings = array (
), $boolean = 'and') {}
    /** @return $this */
    public function orHavingRaw($sql, array $bindings = array (
)) {}
    /** @return $this */
    public function orderBy($column, $direction = 'asc') {}
    /** @return $this */
    public function orderByDesc($column) {}
    /** @return $this */
    public function latest($column = 'created_at') {}
    /** @return $this */
    public function oldest($column = 'created_at') {}
    /** @return $this */
    public function orderByVectorDistance($column, $vector) {}
    /** @return $this */
    public function inRandomOrder($seed = '') {}
    /** @return $this */
    public function inOrderOf($column, $values) {}
    /** @return $this */
    public function orderByRaw($sql, $bindings = array (
)) {}
    /** @return $this */
    public function skip($value) {}
    /** @return $this */
    public function offset($value) {}
    /** @return $this */
    public function take($value) {}
    /** @return $this */
    public function limit($value) {}
    /** @return $this */
    public function groupLimit($value, $column) {}
    /** @return $this */
    public function forPage($page, $perPage = 15) {}
    /** @return $this */
    public function forPageBeforeId($perPage = 15, $lastId = 0, $column = 'id') {}
    /** @return $this */
    public function forPageAfterId($perPage = 15, $lastId = 0, $column = 'id') {}
    /** @return $this */
    public function reorder($column = NULL, $direction = 'asc') {}
    /** @return $this */
    public function reorderDesc($column) {}
    /** @return array */
    protected function removeExistingOrdersFor($column) {}
    /** @return $this */
    public function union($query, $all = false) {}
    /** @return $this */
    public function unionAll($query) {}
    /** @return $this */
    public function lock($value = true) {}
    /** @return $this */
    public function lockForUpdate() {}
    /** @return $this */
    public function sharedLock() {}
    public function timeout(?int $seconds): static {}
    /** @return $this */
    public function beforeQuery(callable $callback) {}
    /** @return void */
    public function applyBeforeQueryCallbacks() {}
    /** @return $this */
    public function afterQuery(\Closure $callback) {}
    /** @return mixed */
    public function applyAfterQueryCallbacks($result) {}
    /** @return string */
    public function toSql() {}
    /** @return string */
    public function toRawSql() {}
    /** @return \stdClass|null */
    public function find($id, $columns = array (
  0 => '*',
)) {}
    /** @return \stdClass|TValue */
    public function findOr($id, $columns = array (
  0 => '*',
), ?\Closure $callback = NULL) {}
    /** @return mixed */
    public function value($column) {}
    /** @return mixed */
    public function rawValue(string $expression, array $bindings = array (
)) {}
    /** @return mixed */
    public function soleValue($column) {}
    /** @return \Illuminate\Support\Collection<int, */
    public function get($columns = array (
  0 => '*',
)) {}
    /** @return array */
    protected function runSelect() {}
    /** @return \Illuminate\Support\Collection */
    protected function withoutGroupLimitKeys($items) {}
    /** @return \Illuminate\Pagination\LengthAwarePaginator */
    public function paginate($perPage = 15, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL, $total = NULL) {}
    /** @return \Illuminate\Contracts\Pagination\Paginator */
    public function simplePaginate($perPage = 15, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL) {}
    /** @return \Illuminate\Contracts\Pagination\CursorPaginator */
    public function cursorPaginate($perPage = 15, $columns = array (
  0 => '*',
), $cursorName = 'cursor', $cursor = NULL) {}
    /** @return \Illuminate\Support\Collection */
    protected function ensureOrderForCursorPagination($shouldReverse = false) {}
    /** @return int<0, */
    public function getCountForPagination($columns = array (
  0 => '*',
)) {}
    /** @return array<mixed> */
    protected function runPaginationCountQuery($columns = array (
  0 => '*',
)) {}
    /** @return self */
    protected function cloneForPaginationCount() {}
    /** @return array<string|\Illuminate\Contracts\Database\Query\Expression> */
    protected function withoutSelectAliases(array $columns) {}
    /** @return \Illuminate\Support\LazyCollection<int, */
    public function cursor() {}
    /** @return void */
    protected function enforceOrderBy() {}
    /** @return \Illuminate\Support\Collection<array-key, */
    public function pluck($column, $key = NULL) {}
    /** @return string|null */
    protected function stripTableForPluck($column) {}
    /** @return \Illuminate\Support\Collection */
    protected function pluckFromObjectColumn($queryResult, $column, $key) {}
    /** @return \Illuminate\Support\Collection */
    protected function pluckFromArrayColumn($queryResult, $column, $key) {}
    /** @return string */
    public function implode($column, $glue = '') {}
    /** @return bool */
    public function exists() {}
    /** @return bool */
    public function doesntExist() {}
    /** @return mixed */
    public function existsOr(\Closure $callback) {}
    /** @return mixed */
    public function doesntExistOr(\Closure $callback) {}
    /** @return int<0, */
    public function count($columns = '*') {}
    /** @return mixed */
    public function min($column) {}
    /** @return mixed */
    public function max($column) {}
    /** @return mixed */
    public function sum($column) {}
    /** @return mixed */
    public function avg($column) {}
    /** @return mixed */
    public function average($column) {}
    /** @return mixed */
    public function aggregate($function, $columns = array (
  0 => '*',
)) {}
    /** @return float|int */
    public function numericAggregate($function, $columns = array (
  0 => '*',
)) {}
    /** @return $this */
    protected function setAggregate($function, $columns) {}
    /** @return TResult */
    protected function onceWithColumns($columns, $callback) {}
    /** @return bool */
    public function insert(array $values) {}
    /** @return int<0, */
    public function insertOrIgnore(array $values) {}
    /** @return int */
    public function insertGetId(array $values, $sequence = NULL) {}
    /** @return int */
    public function insertUsing(array $columns, $query) {}
    /** @return int */
    public function insertOrIgnoreUsing(array $columns, $query) {}
    /** @return int<0, */
    public function update(array $values) {}
    /** @return int */
    public function updateFrom(array $values) {}
    /** @return bool */
    public function updateOrInsert(array $attributes, callable|array $values = array (
)) {}
    /** @return int */
    public function upsert(array $values, array|string $uniqueBy, ?array $update = NULL) {}
    /** @return int<0, */
    public function increment($column, $amount = 1, array $extra = array (
)) {}
    /** @return int<0, */
    public function incrementEach(array $columns, array $extra = array (
)) {}
    /** @return int<0, */
    public function decrement($column, $amount = 1, array $extra = array (
)) {}
    /** @return int<0, */
    public function decrementEach(array $columns, array $extra = array (
)) {}
    /** @return int */
    public function delete($id = NULL) {}
    /** @return void */
    public function truncate() {}
    /** @return \Illuminate\Database\Query\Builder */
    public function newQuery() {}
    /** @return \Illuminate\Database\Query\Builder */
    protected function forSubQuery() {}
    /** @return list<string> */
    public function getColumns() {}
    /** @return \Illuminate\Contracts\Database\Query\Expression */
    public function raw($value) {}
    /** @return \Illuminate\Support\Collection */
    protected function getUnionBuilders() {}
    /** @return mixed */
    public function getLimit() {}
    /** @return mixed */
    public function getOffset() {}
    /** @return list<mixed> */
    public function getBindings() {}
    /** @return array{ */
    public function getRawBindings() {}
    /** @return $this */
    public function setBindings(array $bindings, $type = 'where') {}
    /** @return $this */
    public function addBinding($value, $type = 'where') {}
    /** @return mixed */
    public function castBinding($value) {}
    /** @return $this */
    public function mergeBindings(\Illuminate\Database\Query\Builder $query) {}
    /** @return list<mixed> */
    public function cleanBindings(array $bindings) {}
    /** @return mixed */
    protected function flattenValue($value) {}
    /** @return string */
    protected function defaultKeyName() {}
    /** @return \Illuminate\Database\ConnectionInterface */
    public function getConnection() {}
    /** @return void */
    protected function ensureConnectionSupportsVectors() {}
    /** @return \Illuminate\Database\Query\Processors\Processor */
    public function getProcessor() {}
    /** @return \Illuminate\Database\Query\Grammars\Grammar */
    public function getGrammar() {}
    /** @return $this */
    public function useWritePdo() {}
    /** @return bool */
    protected function isQueryable($value) {}
    /** @return static */
    public function clone() {}
    /** @return static */
    public function cloneWithout(array $properties) {}
    /** @return static */
    public function cloneWithoutBindings(array $except) {}
    /** @return $this */
    public function dump(...$args) {}
    /** @return $this */
    public function dumpRawSql() {}
    /** @return never */
    public function dd() {}
    /** @return never */
    public function ddRawSql() {}
    /** @return mixed */
    public function __call($method, $parameters) {}
    /** @return mixed */
    public function macroCall($method, $parameters) {}
}
