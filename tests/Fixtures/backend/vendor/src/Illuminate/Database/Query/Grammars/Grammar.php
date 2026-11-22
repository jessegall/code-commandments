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
    /** @return string */
    public function compileSelect(\Illuminate\Database\Query\Builder $query) {}
    /** @return array */
    protected function compileComponents(\Illuminate\Database\Query\Builder $query) {}
    /** @return string */
    protected function compileAggregate(\Illuminate\Database\Query\Builder $query, $aggregate) {}
    /** @return string|null */
    protected function compileColumns(\Illuminate\Database\Query\Builder $query, $columns) {}
    /** @return string */
    protected function compileFrom(\Illuminate\Database\Query\Builder $query, $table) {}
    /** @return string */
    protected function compileJoins(\Illuminate\Database\Query\Builder $query, $joins) {}
    public function compileJoinLateral(\Illuminate\Database\Query\JoinLateralClause $join, string $expression): string {}
    /** @return string */
    public function compileWheres(\Illuminate\Database\Query\Builder $query) {}
    /** @return array */
    protected function compileWheresToArray($query) {}
    /** @return string */
    protected function concatenateWhereClauses($query, $sql) {}
    /** @return string */
    protected function whereRaw(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereBasic(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereBitwise(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereLike(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereNullSafeEquals(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereIn(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereNotIn(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereNotInRaw(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereInRaw(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereNull(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereNotNull(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereBetween(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereBetweenColumns(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereValueBetween(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereDate(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereTime(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereDay(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereMonth(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereYear(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function dateBasedWhere($type, \Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereColumn(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereNested(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereSub(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereExists(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereNotExists(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereRowValues(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereJsonBoolean(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function whereJsonContains(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function compileJsonContains($column, $value) {}
    /** @return string */
    protected function whereJsonOverlaps(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function compileJsonOverlaps($column, $value) {}
    /** @return string */
    public function prepareBindingForJsonContains($binding) {}
    /** @return string */
    protected function whereJsonContainsKey(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function compileJsonContainsKey($column) {}
    /** @return string */
    protected function whereJsonLength(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function compileJsonLength($column, $operator, $value) {}
    /** @return string */
    public function compileJsonValueCast($value) {}
    /** @return string */
    public function whereFullText(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    public function whereExpression(\Illuminate\Database\Query\Builder $query, $where) {}
    /** @return string */
    protected function compileGroups(\Illuminate\Database\Query\Builder $query, $groups) {}
    /** @return string */
    protected function compileHavings(\Illuminate\Database\Query\Builder $query) {}
    /** @return string */
    protected function compileHaving(array $having) {}
    /** @return string */
    protected function compileBasicHaving($having) {}
    /** @return string */
    protected function compileHavingBetween($having) {}
    /** @return string */
    protected function compileHavingNull($having) {}
    /** @return string */
    protected function compileHavingNotNull($having) {}
    /** @return string */
    protected function compileHavingBit($having) {}
    /** @return string */
    protected function compileHavingExpression($having) {}
    /** @return string */
    protected function compileNestedHavings($having) {}
    /** @return string */
    protected function compileOrders(\Illuminate\Database\Query\Builder $query, $orders) {}
    /** @return array */
    protected function compileOrdersToArray(\Illuminate\Database\Query\Builder $query, $orders) {}
    /** @return string */
    protected function compileInOrderOf($order) {}
    /** @return string */
    public function compileRandom($seed) {}
    /** @return string */
    protected function compileLimit(\Illuminate\Database\Query\Builder $query, $limit) {}
    /** @return string */
    protected function compileGroupLimit(\Illuminate\Database\Query\Builder $query) {}
    /** @return string */
    protected function compileRowNumber($partition, $orders) {}
    /** @return string */
    protected function compileOffset(\Illuminate\Database\Query\Builder $query, $offset) {}
    /** @return string */
    protected function compileUnions(\Illuminate\Database\Query\Builder $query) {}
    /** @return string */
    protected function compileUnion(array $union) {}
    /** @return string */
    protected function wrapUnion($sql) {}
    /** @return string */
    protected function compileUnionAggregate(\Illuminate\Database\Query\Builder $query) {}
    /** @return string */
    public function compileExists(\Illuminate\Database\Query\Builder $query) {}
    /** @return string */
    public function compileInsert(\Illuminate\Database\Query\Builder $query, array $values) {}
    /** @return string */
    public function compileInsertOrIgnore(\Illuminate\Database\Query\Builder $query, array $values) {}
    /** @return string */
    public function compileInsertGetId(\Illuminate\Database\Query\Builder $query, $values, $sequence) {}
    /** @return string */
    public function compileInsertUsing(\Illuminate\Database\Query\Builder $query, array $columns, string $sql) {}
    /** @return string */
    public function compileInsertOrIgnoreUsing(\Illuminate\Database\Query\Builder $query, array $columns, string $sql) {}
    /** @return string */
    public function compileUpdate(\Illuminate\Database\Query\Builder $query, array $values) {}
    /** @return string */
    protected function compileUpdateColumns(\Illuminate\Database\Query\Builder $query, array $values) {}
    /** @return string */
    protected function compileUpdateWithoutJoins(\Illuminate\Database\Query\Builder $query, $table, $columns, $where) {}
    /** @return string */
    protected function compileUpdateWithJoins(\Illuminate\Database\Query\Builder $query, $table, $columns, $where) {}
    /** @return string */
    public function compileUpsert(\Illuminate\Database\Query\Builder $query, array $values, array $uniqueBy, array $update) {}
    /** @return array */
    public function prepareBindingsForUpdate(array $bindings, array $values) {}
    /** @return string */
    public function compileDelete(\Illuminate\Database\Query\Builder $query) {}
    /** @return string */
    protected function compileDeleteWithoutJoins(\Illuminate\Database\Query\Builder $query, $table, $where) {}
    /** @return string */
    protected function compileDeleteWithJoins(\Illuminate\Database\Query\Builder $query, $table, $where) {}
    /** @return array */
    public function prepareBindingsForDelete(array $bindings) {}
    /** @return array */
    public function compileTruncate(\Illuminate\Database\Query\Builder $query) {}
    /** @return string */
    protected function compileLock(\Illuminate\Database\Query\Builder $query, $value) {}
    /** @return string|null */
    public function compileThreadCount() {}
    /** @return bool */
    public function supportsSavepoints() {}
    /** @return string */
    public function compileSavepoint($name) {}
    /** @return string */
    public function compileSavepointRollBack($name) {}
    /** @return string */
    protected function wrapJsonBooleanSelector($value) {}
    /** @return string */
    protected function wrapJsonBooleanValue($value) {}
    /** @return string */
    protected function concatenate($segments) {}
    /** @return string */
    protected function removeLeadingBoolean($value) {}
    /** @return string */
    public function substituteBindingsIntoRawSql($sql, $bindings) {}
    /** @return array */
    public function getOperators() {}
    /** @return array */
    public function getBitwiseOperators() {}
}
