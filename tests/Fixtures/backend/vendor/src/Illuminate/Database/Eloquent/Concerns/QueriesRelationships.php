<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait QueriesRelationships
{
    public function has($relation, $operator = '>=', $count = 1, $boolean = 'and', ?\Closure $callback = NULL) {}
    protected function hasNested($relations, $operator = '>=', $count = 1, $boolean = 'and', $callback = NULL) {}
    public function orHas($relation, $operator = '>=', $count = 1) {}
    public function doesntHave($relation, $boolean = 'and', ?\Closure $callback = NULL) {}
    public function orDoesntHave($relation) {}
    public function whereHas($relation, ?\Closure $callback = NULL, $operator = '>=', $count = 1) {}
    public function withWhereHas($relation, ?\Closure $callback = NULL, $operator = '>=', $count = 1) {}
    public function orWhereHas($relation, ?\Closure $callback = NULL, $operator = '>=', $count = 1) {}
    public function whereDoesntHave($relation, ?\Closure $callback = NULL) {}
    public function orWhereDoesntHave($relation, ?\Closure $callback = NULL) {}
    public function hasMorph($relation, $types, $operator = '>=', $count = 1, $boolean = 'and', ?\Closure $callback = NULL) {}
    protected function getBelongsToRelation(\Illuminate\Database\Eloquent\Relations\MorphTo $relation, $type) {}
    public function orHasMorph($relation, $types, $operator = '>=', $count = 1) {}
    public function doesntHaveMorph($relation, $types, $boolean = 'and', ?\Closure $callback = NULL) {}
    public function orDoesntHaveMorph($relation, $types) {}
    public function whereHasMorph($relation, $types, ?\Closure $callback = NULL, $operator = '>=', $count = 1) {}
    public function orWhereHasMorph($relation, $types, ?\Closure $callback = NULL, $operator = '>=', $count = 1) {}
    public function whereDoesntHaveMorph($relation, $types, ?\Closure $callback = NULL) {}
    public function orWhereDoesntHaveMorph($relation, $types, ?\Closure $callback = NULL) {}
    public function whereRelation($relation, $column, $operator = NULL, $value = NULL) {}
    public function withWhereRelation($relation, $column, $operator = NULL, $value = NULL) {}
    public function orWhereRelation($relation, $column, $operator = NULL, $value = NULL) {}
    public function whereDoesntHaveRelation($relation, $column, $operator = NULL, $value = NULL) {}
    public function orWhereDoesntHaveRelation($relation, $column, $operator = NULL, $value = NULL) {}
    public function whereMorphRelation($relation, $types, $column, $operator = NULL, $value = NULL) {}
    public function orWhereMorphRelation($relation, $types, $column, $operator = NULL, $value = NULL) {}
    public function whereMorphDoesntHaveRelation($relation, $types, $column, $operator = NULL, $value = NULL) {}
    public function orWhereMorphDoesntHaveRelation($relation, $types, $column, $operator = NULL, $value = NULL) {}
    public function whereMorphedTo($relation, $model, $boolean = 'and') {}
    public function whereNotMorphedTo($relation, $model, $boolean = 'and') {}
    public function orWhereMorphedTo($relation, $model) {}
    public function orWhereNotMorphedTo($relation, $model) {}
    public function whereBelongsTo($related, $relationshipName = NULL, $boolean = 'and') {}
    public function orWhereBelongsTo($related, $relationshipName = NULL) {}
    public function whereAttachedTo($related, $relationshipName = NULL, $boolean = 'and') {}
    public function orWhereAttachedTo($related, $relationshipName = NULL) {}
    public function withAggregate($relations, $column, $function = NULL) {}
    protected function getRelationHashedColumn($column, $relation) {}
    public function withCount($relations) {}
    public function withMax($relation, $column) {}
    public function withMin($relation, $column) {}
    public function withSum($relation, $column) {}
    public function withAvg($relation, $column) {}
    public function withExists($relation) {}
    protected function addHasWhere(\Illuminate\Database\Eloquent\Builder $hasQuery, \Illuminate\Database\Eloquent\Relations\Relation $relation, $operator, $count, $boolean) {}
    public function mergeConstraintsFrom(\Illuminate\Database\Eloquent\Builder $from) {}
    protected function requalifyWhereTables(array $wheres, string $from, string $to): array {}
    protected function addWhereCountQuery(\Illuminate\Database\Query\Builder $query, $operator = '>=', $count = 1, $boolean = 'and') {}
    protected function getRelationWithoutConstraints($relation) {}
    protected function canUseExistsForExistenceCheck($operator, $count) {}
}
