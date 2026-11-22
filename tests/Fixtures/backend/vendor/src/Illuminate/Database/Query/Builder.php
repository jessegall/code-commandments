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
    public function select($columns = array (
  0 => '*',
)) {}
    public function selectSub($query, $as) {}
    public function selectExpression($expression, $as) {}
    public function selectRaw($expression, array $bindings = array (
)) {}
    public function fromSub($query, $as) {}
    public function fromRaw($expression, $bindings = array (
)) {}
    protected function createSub($query) {}
    protected function parseSub($query) {}
    protected function prependDatabaseNameIfCrossDatabaseQuery($query) {}
    public function addSelect($column) {}
    public function selectVectorDistance($column, $vector, $as = NULL) {}
    public function distinct() {}
    public function from($table, $as = NULL) {}
    public function useIndex($index) {}
    public function forceIndex($index) {}
    public function ignoreIndex($index) {}
    public function join($table, $first, $operator = NULL, $second = NULL, $type = 'inner', $where = false) {}
    public function joinWhere($table, $first, $operator, $second, $type = 'inner') {}
    public function joinSub($query, $as, $first, $operator = NULL, $second = NULL, $type = 'inner', $where = false) {}
    public function joinLateral($query, string $as, string $type = 'inner') {}
    public function leftJoinLateral($query, string $as) {}
    public function leftJoin($table, $first, $operator = NULL, $second = NULL) {}
    public function leftJoinWhere($table, $first, $operator, $second) {}
    public function leftJoinSub($query, $as, $first, $operator = NULL, $second = NULL) {}
    public function rightJoin($table, $first, $operator = NULL, $second = NULL) {}
    public function rightJoinWhere($table, $first, $operator, $second) {}
    public function rightJoinSub($query, $as, $first, $operator = NULL, $second = NULL) {}
    public function crossJoin($table, $first = NULL, $operator = NULL, $second = NULL) {}
    public function crossJoinSub($query, $as) {}
    protected function newJoinClause(\Illuminate\Database\Query\Builder $parentQuery, $type, $table) {}
    protected function newJoinLateralClause(\Illuminate\Database\Query\Builder $parentQuery, $type, $table) {}
    public function mergeWheres($wheres, $bindings) {}
    public function where($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    protected function addArrayOfWheres($column, $boolean, $method = 'where') {}
    public function prepareValueAndOperator($value, $operator, $useDefault = false) {}
    protected function invalidOperatorAndValue($operator, $value) {}
    protected function invalidOperator($operator) {}
    protected function isBitwiseOperator($operator) {}
    public function orWhere($column, $operator = NULL, $value = NULL) {}
    public function whereNot($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    public function orWhereNot($column, $operator = NULL, $value = NULL) {}
    public function whereColumn($first, $operator = NULL, $second = NULL, $boolean = 'and') {}
    public function orWhereColumn($first, $operator = NULL, $second = NULL) {}
    public function whereVectorSimilarTo($column, $vector, $minSimilarity = 0.6, $order = true) {}
    public function whereVectorDistanceLessThan($column, $vector, $maxDistance, $boolean = 'and') {}
    public function orWhereVectorDistanceLessThan($column, $vector, $maxDistance) {}
    public function whereRaw($sql, $bindings = array (
), $boolean = 'and') {}
    public function orWhereRaw($sql, $bindings = array (
)) {}
    public function whereLike($column, $value, $caseSensitive = false, $boolean = 'and', $not = false) {}
    public function orWhereLike($column, $value, $caseSensitive = false) {}
    public function whereNotLike($column, $value, $caseSensitive = false, $boolean = 'and') {}
    public function orWhereNotLike($column, $value, $caseSensitive = false) {}
    public function whereNullSafeEquals($column, $value, $boolean = 'and') {}
    public function orWhereNullSafeEquals($column, $value) {}
    public function whereIn($column, $values, $boolean = 'and', $not = false) {}
    public function orWhereIn($column, $values) {}
    public function whereNotIn($column, $values, $boolean = 'and') {}
    public function orWhereNotIn($column, $values) {}
    public function whereIntegerInRaw($column, $values, $boolean = 'and', $not = false) {}
    public function orWhereIntegerInRaw($column, $values) {}
    public function whereIntegerNotInRaw($column, $values, $boolean = 'and') {}
    public function orWhereIntegerNotInRaw($column, $values) {}
    public function whereNull($columns, $boolean = 'and', $not = false) {}
    public function orWhereNull($column) {}
    public function whereNotNull($columns, $boolean = 'and') {}
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false) {}
    public function whereBetweenColumns($column, array $values, $boolean = 'and', $not = false) {}
    public function orWhereBetween($column, iterable $values) {}
    public function orWhereBetweenColumns($column, array $values) {}
    public function whereNotBetween($column, iterable $values, $boolean = 'and') {}
    public function whereNotBetweenColumns($column, array $values, $boolean = 'and') {}
    public function orWhereNotBetween($column, iterable $values) {}
    public function orWhereNotBetweenColumns($column, array $values) {}
    public function whereValueBetween($value, array $columns, $boolean = 'and', $not = false) {}
    public function orWhereValueBetween($value, array $columns) {}
    public function whereValueNotBetween($value, array $columns, $boolean = 'and') {}
    public function orWhereValueNotBetween($value, array $columns) {}
    public function orWhereNotNull($column) {}
    public function whereDate($column, $operator, $value = NULL, $boolean = 'and') {}
    public function orWhereDate($column, $operator, $value = NULL) {}
    public function whereTime($column, $operator, $value = NULL, $boolean = 'and') {}
    public function orWhereTime($column, $operator, $value = NULL) {}
    public function whereDay($column, $operator, $value = NULL, $boolean = 'and') {}
    public function orWhereDay($column, $operator, $value = NULL) {}
    public function whereMonth($column, $operator, $value = NULL, $boolean = 'and') {}
    public function orWhereMonth($column, $operator, $value = NULL) {}
    public function whereYear($column, $operator, $value = NULL, $boolean = 'and') {}
    public function orWhereYear($column, $operator, $value = NULL) {}
    protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and') {}
    public function whereNested(\Closure $callback, $boolean = 'and') {}
    public function forNestedWhere() {}
    public function addNestedWhereQuery($query, $boolean = 'and') {}
    protected function whereSub($column, $operator, $callback, $boolean) {}
    public function whereExists($callback, $boolean = 'and', $not = false) {}
    public function orWhereExists($callback, $not = false) {}
    public function whereNotExists($callback, $boolean = 'and') {}
    public function orWhereNotExists($callback) {}
    public function addWhereExistsQuery(\Illuminate\Database\Query\Builder $query, $boolean = 'and', $not = false) {}
    public function whereRowValues($columns, $operator, $values, $boolean = 'and') {}
    public function orWhereRowValues($columns, $operator, $values) {}
    public function whereJsonContains($column, $value, $boolean = 'and', $not = false) {}
    public function orWhereJsonContains($column, $value) {}
    public function whereJsonDoesntContain($column, $value, $boolean = 'and') {}
    public function orWhereJsonDoesntContain($column, $value) {}
    public function whereJsonOverlaps($column, $value, $boolean = 'and', $not = false) {}
    public function orWhereJsonOverlaps($column, $value) {}
    public function whereJsonDoesntOverlap($column, $value, $boolean = 'and') {}
    public function orWhereJsonDoesntOverlap($column, $value) {}
    public function whereJsonContainsKey($column, $boolean = 'and', $not = false) {}
    public function orWhereJsonContainsKey($column) {}
    public function whereJsonDoesntContainKey($column, $boolean = 'and') {}
    public function orWhereJsonDoesntContainKey($column) {}
    public function whereJsonLength($column, $operator, $value = NULL, $boolean = 'and') {}
    public function orWhereJsonLength($column, $operator, $value = NULL) {}
    public function dynamicWhere($method, $parameters) {}
    protected function addDynamic($segment, $connector, $parameters, $index) {}
    public function whereFullText($columns, $value, array $options = array (
), $boolean = 'and') {}
    public function orWhereFullText($columns, $value, array $options = array (
)) {}
    public function whereAll($columns, $operator = NULL, $value = NULL, $boolean = 'and') {}
    public function orWhereAll($columns, $operator = NULL, $value = NULL) {}
    public function whereAny($columns, $operator = NULL, $value = NULL, $boolean = 'and') {}
    public function orWhereAny($columns, $operator = NULL, $value = NULL) {}
    public function whereNone($columns, $operator = NULL, $value = NULL, $boolean = 'and') {}
    public function orWhereNone($columns, $operator = NULL, $value = NULL) {}
    public function groupBy(...$groups) {}
    public function groupByRaw($sql, array $bindings = array (
)) {}
    public function having($column, $operator = NULL, $value = NULL, $boolean = 'and') {}
    public function orHaving($column, $operator = NULL, $value = NULL) {}
    public function havingNested(\Closure $callback, $boolean = 'and') {}
    public function addNestedHavingQuery($query, $boolean = 'and') {}
    public function havingNull($columns, $boolean = 'and', $not = false) {}
    public function orHavingNull($column) {}
    public function havingNotNull($columns, $boolean = 'and') {}
    public function orHavingNotNull($column) {}
    public function havingBetween($column, iterable $values, $boolean = 'and', $not = false) {}
    public function havingNotBetween($column, iterable $values, $boolean = 'and') {}
    public function orHavingBetween($column, iterable $values) {}
    public function orHavingNotBetween($column, iterable $values) {}
    protected function resolveDatePeriodBounds(\DatePeriod $period) {}
    public function havingRaw($sql, array $bindings = array (
), $boolean = 'and') {}
    public function orHavingRaw($sql, array $bindings = array (
)) {}
    public function orderBy($column, $direction = 'asc') {}
    public function orderByDesc($column) {}
    public function latest($column = 'created_at') {}
    public function oldest($column = 'created_at') {}
    public function orderByVectorDistance($column, $vector) {}
    public function inRandomOrder($seed = '') {}
    public function inOrderOf($column, $values) {}
    public function orderByRaw($sql, $bindings = array (
)) {}
    public function skip($value) {}
    public function offset($value) {}
    public function take($value) {}
    public function limit($value) {}
    public function groupLimit($value, $column) {}
    public function forPage($page, $perPage = 15) {}
    public function forPageBeforeId($perPage = 15, $lastId = 0, $column = 'id') {}
    public function forPageAfterId($perPage = 15, $lastId = 0, $column = 'id') {}
    public function reorder($column = NULL, $direction = 'asc') {}
    public function reorderDesc($column) {}
    protected function removeExistingOrdersFor($column) {}
    public function union($query, $all = false) {}
    public function unionAll($query) {}
    public function lock($value = true) {}
    public function lockForUpdate() {}
    public function sharedLock() {}
    public function timeout(?int $seconds): static {}
    public function beforeQuery(callable $callback) {}
    public function applyBeforeQueryCallbacks() {}
    public function afterQuery(\Closure $callback) {}
    public function applyAfterQueryCallbacks($result) {}
    public function toSql() {}
    public function toRawSql() {}
    public function find($id, $columns = array (
  0 => '*',
)) {}
    public function findOr($id, $columns = array (
  0 => '*',
), ?\Closure $callback = NULL) {}
    public function value($column) {}
    public function rawValue(string $expression, array $bindings = array (
)) {}
    public function soleValue($column) {}
    public function get($columns = array (
  0 => '*',
)) {}
    protected function runSelect() {}
    protected function withoutGroupLimitKeys($items) {}
    public function paginate($perPage = 15, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL, $total = NULL) {}
    public function simplePaginate($perPage = 15, $columns = array (
  0 => '*',
), $pageName = 'page', $page = NULL) {}
    public function cursorPaginate($perPage = 15, $columns = array (
  0 => '*',
), $cursorName = 'cursor', $cursor = NULL) {}
    protected function ensureOrderForCursorPagination($shouldReverse = false) {}
    public function getCountForPagination($columns = array (
  0 => '*',
)) {}
    protected function runPaginationCountQuery($columns = array (
  0 => '*',
)) {}
    protected function cloneForPaginationCount() {}
    protected function withoutSelectAliases(array $columns) {}
    public function cursor() {}
    protected function enforceOrderBy() {}
    public function pluck($column, $key = NULL) {}
    protected function stripTableForPluck($column) {}
    protected function pluckFromObjectColumn($queryResult, $column, $key) {}
    protected function pluckFromArrayColumn($queryResult, $column, $key) {}
    public function implode($column, $glue = '') {}
    public function exists() {}
    public function doesntExist() {}
    public function existsOr(\Closure $callback) {}
    public function doesntExistOr(\Closure $callback) {}
    public function count($columns = '*') {}
    public function min($column) {}
    public function max($column) {}
    public function sum($column) {}
    public function avg($column) {}
    public function average($column) {}
    public function aggregate($function, $columns = array (
  0 => '*',
)) {}
    public function numericAggregate($function, $columns = array (
  0 => '*',
)) {}
    protected function setAggregate($function, $columns) {}
    protected function onceWithColumns($columns, $callback) {}
    public function insert(array $values) {}
    public function insertOrIgnore(array $values) {}
    public function insertGetId(array $values, $sequence = NULL) {}
    public function insertUsing(array $columns, $query) {}
    public function insertOrIgnoreUsing(array $columns, $query) {}
    public function update(array $values) {}
    public function updateFrom(array $values) {}
    public function updateOrInsert(array $attributes, callable|array $values = array (
)) {}
    public function upsert(array $values, array|string $uniqueBy, ?array $update = NULL) {}
    public function increment($column, $amount = 1, array $extra = array (
)) {}
    public function incrementEach(array $columns, array $extra = array (
)) {}
    public function decrement($column, $amount = 1, array $extra = array (
)) {}
    public function decrementEach(array $columns, array $extra = array (
)) {}
    public function delete($id = NULL) {}
    public function truncate() {}
    public function newQuery() {}
    protected function forSubQuery() {}
    public function getColumns() {}
    public function raw($value) {}
    protected function getUnionBuilders() {}
    public function getLimit() {}
    public function getOffset() {}
    public function getBindings() {}
    public function getRawBindings() {}
    public function setBindings(array $bindings, $type = 'where') {}
    public function addBinding($value, $type = 'where') {}
    public function castBinding($value) {}
    public function mergeBindings(\Illuminate\Database\Query\Builder $query) {}
    public function cleanBindings(array $bindings) {}
    protected function flattenValue($value) {}
    protected function defaultKeyName() {}
    public function getConnection() {}
    protected function ensureConnectionSupportsVectors() {}
    public function getProcessor() {}
    public function getGrammar() {}
    public function useWritePdo() {}
    protected function isQueryable($value) {}
    public function clone() {}
    public function cloneWithout(array $properties) {}
    public function cloneWithoutBindings(array $except) {}
    public function dump(...$args) {}
    public function dumpRawSql() {}
    public function dd() {}
    public function ddRawSql() {}
    public function __call($method, $parameters) {}
    public function macroCall($method, $parameters) {}
}
