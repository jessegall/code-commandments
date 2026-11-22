<?php

namespace Illuminate\Database\Query\Grammars;

class Grammar extends \Illuminate\Database\Grammar
{
    use \Illuminate\Database\Concerns\CompilesJsonPaths;
    protected $operators = array (
);
    protected $bitwiseOperators = array (
);
    protected $selectComponents = array (
  0 => 'aggregate',
  1 => 'columns',
  2 => 'from',
  3 => 'indexHint',
  4 => 'joins',
  5 => 'wheres',
  6 => 'groups',
  7 => 'havings',
  8 => 'orders',
  9 => 'limit',
  10 => 'offset',
  11 => 'lock',
);
    public function compileSelect(\Illuminate\Database\Query\Builder $query) {}
    protected function compileComponents(\Illuminate\Database\Query\Builder $query) {}
    protected function compileAggregate(\Illuminate\Database\Query\Builder $query, $aggregate) {}
    protected function compileColumns(\Illuminate\Database\Query\Builder $query, $columns) {}
    protected function compileFrom(\Illuminate\Database\Query\Builder $query, $table) {}
    protected function compileJoins(\Illuminate\Database\Query\Builder $query, $joins) {}
    public function compileJoinLateral(\Illuminate\Database\Query\JoinLateralClause $join, string $expression): string {}
    public function compileWheres(\Illuminate\Database\Query\Builder $query) {}
    protected function compileWheresToArray($query) {}
    protected function concatenateWhereClauses($query, $sql) {}
    protected function whereRaw(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereBasic(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereBitwise(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereLike(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereNullSafeEquals(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereIn(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereNotIn(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereNotInRaw(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereInRaw(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereNull(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereNotNull(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereBetween(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereBetweenColumns(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereValueBetween(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereDate(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereTime(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereDay(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereMonth(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereYear(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function dateBasedWhere($type, \Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereColumn(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereNested(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereSub(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereExists(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereNotExists(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereRowValues(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereJsonBoolean(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function whereJsonContains(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function compileJsonContains($column, $value) {}
    protected function whereJsonOverlaps(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function compileJsonOverlaps($column, $value) {}
    public function prepareBindingForJsonContains($binding) {}
    protected function whereJsonContainsKey(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function compileJsonContainsKey($column) {}
    protected function whereJsonLength(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function compileJsonLength($column, $operator, $value) {}
    public function compileJsonValueCast($value) {}
    public function whereFullText(\Illuminate\Database\Query\Builder $query, $where) {}
    public function whereExpression(\Illuminate\Database\Query\Builder $query, $where) {}
    protected function compileGroups(\Illuminate\Database\Query\Builder $query, $groups) {}
    protected function compileHavings(\Illuminate\Database\Query\Builder $query) {}
    protected function compileHaving(array $having) {}
    protected function compileBasicHaving($having) {}
    protected function compileHavingBetween($having) {}
    protected function compileHavingNull($having) {}
    protected function compileHavingNotNull($having) {}
    protected function compileHavingBit($having) {}
    protected function compileHavingExpression($having) {}
    protected function compileNestedHavings($having) {}
    protected function compileOrders(\Illuminate\Database\Query\Builder $query, $orders) {}
    protected function compileOrdersToArray(\Illuminate\Database\Query\Builder $query, $orders) {}
    protected function compileInOrderOf($order) {}
    public function compileRandom($seed) {}
    protected function compileLimit(\Illuminate\Database\Query\Builder $query, $limit) {}
    protected function compileGroupLimit(\Illuminate\Database\Query\Builder $query) {}
    protected function compileRowNumber($partition, $orders) {}
    protected function compileOffset(\Illuminate\Database\Query\Builder $query, $offset) {}
    protected function compileUnions(\Illuminate\Database\Query\Builder $query) {}
    protected function compileUnion(array $union) {}
    protected function wrapUnion($sql) {}
    protected function compileUnionAggregate(\Illuminate\Database\Query\Builder $query) {}
    public function compileExists(\Illuminate\Database\Query\Builder $query) {}
    public function compileInsert(\Illuminate\Database\Query\Builder $query, array $values) {}
    public function compileInsertOrIgnore(\Illuminate\Database\Query\Builder $query, array $values) {}
    public function compileInsertGetId(\Illuminate\Database\Query\Builder $query, $values, $sequence) {}
    public function compileInsertUsing(\Illuminate\Database\Query\Builder $query, array $columns, string $sql) {}
    public function compileInsertOrIgnoreUsing(\Illuminate\Database\Query\Builder $query, array $columns, string $sql) {}
    public function compileUpdate(\Illuminate\Database\Query\Builder $query, array $values) {}
    protected function compileUpdateColumns(\Illuminate\Database\Query\Builder $query, array $values) {}
    protected function compileUpdateWithoutJoins(\Illuminate\Database\Query\Builder $query, $table, $columns, $where) {}
    protected function compileUpdateWithJoins(\Illuminate\Database\Query\Builder $query, $table, $columns, $where) {}
    public function compileUpsert(\Illuminate\Database\Query\Builder $query, array $values, array $uniqueBy, array $update) {}
    public function prepareBindingsForUpdate(array $bindings, array $values) {}
    public function compileDelete(\Illuminate\Database\Query\Builder $query) {}
    protected function compileDeleteWithoutJoins(\Illuminate\Database\Query\Builder $query, $table, $where) {}
    protected function compileDeleteWithJoins(\Illuminate\Database\Query\Builder $query, $table, $where) {}
    public function prepareBindingsForDelete(array $bindings) {}
    public function compileTruncate(\Illuminate\Database\Query\Builder $query) {}
    protected function compileLock(\Illuminate\Database\Query\Builder $query, $value) {}
    public function compileThreadCount() {}
    public function supportsSavepoints() {}
    public function compileSavepoint($name) {}
    public function compileSavepointRollBack($name) {}
    protected function wrapJsonBooleanSelector($value) {}
    protected function wrapJsonBooleanValue($value) {}
    protected function concatenate($segments) {}
    protected function removeLeadingBoolean($value) {}
    public function substituteBindingsIntoRawSql($sql, $bindings) {}
    public function getOperators() {}
    public function getBitwiseOperators() {}
}
