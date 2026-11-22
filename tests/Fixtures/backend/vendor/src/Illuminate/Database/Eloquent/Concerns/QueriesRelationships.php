<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait QueriesRelationships
{
    /** @return $this */
    public function has($relation, $operator = '>=', $count = 1, $boolean = 'and', ?\Closure $callback = NULL) {}
    /** @return $this */
    protected function hasNested($relations, $operator = '>=', $count = 1, $boolean = 'and', $callback = NULL) {}
    /** @return $this */
    public function orHas($relation, $operator = '>=', $count = 1) {}
    /** @return $this */
    public function doesntHave($relation, $boolean = 'and', ?\Closure $callback = NULL) {}
    /** @return $this */
    public function orDoesntHave($relation) {}
    /** @return $this */
    public function whereHas($relation, ?\Closure $callback = NULL, $operator = '>=', $count = 1) {}
    /** @return $this */
    public function withWhereHas($relation, ?\Closure $callback = NULL, $operator = '>=', $count = 1) {}
    /** @return $this */
    public function orWhereHas($relation, ?\Closure $callback = NULL, $operator = '>=', $count = 1) {}
    /** @return $this */
    public function whereDoesntHave($relation, ?\Closure $callback = NULL) {}
    /** @return $this */
    public function orWhereDoesntHave($relation, ?\Closure $callback = NULL) {}
    /** @return $this */
    public function hasMorph($relation, $types, $operator = '>=', $count = 1, $boolean = 'and', ?\Closure $callback = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<TRelatedModel, */
    protected function getBelongsToRelation(\Illuminate\Database\Eloquent\Relations\MorphTo $relation, $type) {}
    /** @return $this */
    public function orHasMorph($relation, $types, $operator = '>=', $count = 1) {}
    /** @return $this */
    public function doesntHaveMorph($relation, $types, $boolean = 'and', ?\Closure $callback = NULL) {}
    /** @return $this */
    public function orDoesntHaveMorph($relation, $types) {}
    /** @return $this */
    public function whereHasMorph($relation, $types, ?\Closure $callback = NULL, $operator = '>=', $count = 1) {}
    /** @return $this */
    public function orWhereHasMorph($relation, $types, ?\Closure $callback = NULL, $operator = '>=', $count = 1) {}
    /** @return $this */
    public function whereDoesntHaveMorph($relation, $types, ?\Closure $callback = NULL) {}
    /** @return $this */
    public function orWhereDoesntHaveMorph($relation, $types, ?\Closure $callback = NULL) {}
    /** @return $this */
    public function whereRelation($relation, $column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function withWhereRelation($relation, $column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function orWhereRelation($relation, $column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function whereDoesntHaveRelation($relation, $column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function orWhereDoesntHaveRelation($relation, $column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function whereMorphRelation($relation, $types, $column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function orWhereMorphRelation($relation, $types, $column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function whereMorphDoesntHaveRelation($relation, $types, $column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function orWhereMorphDoesntHaveRelation($relation, $types, $column, $operator = NULL, $value = NULL) {}
    /** @return $this */
    public function whereMorphedTo($relation, $model, $boolean = 'and') {}
    /** @return $this */
    public function whereNotMorphedTo($relation, $model, $boolean = 'and') {}
    /** @return $this */
    public function orWhereMorphedTo($relation, $model) {}
    /** @return $this */
    public function orWhereNotMorphedTo($relation, $model) {}
    /** @return $this */
    public function whereBelongsTo($related, $relationshipName = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereBelongsTo($related, $relationshipName = NULL) {}
    /** @return $this */
    public function whereAttachedTo($related, $relationshipName = NULL, $boolean = 'and') {}
    /** @return $this */
    public function orWhereAttachedTo($related, $relationshipName = NULL) {}
    /** @return $this */
    public function withAggregate($relations, $column, $function = NULL) {}
    /** @return string */
    protected function getRelationHashedColumn($column, $relation) {}
    /** @return $this */
    public function withCount($relations) {}
    /** @return $this */
    public function withMax($relation, $column) {}
    /** @return $this */
    public function withMin($relation, $column) {}
    /** @return $this */
    public function withSum($relation, $column) {}
    /** @return $this */
    public function withAvg($relation, $column) {}
    /** @return $this */
    public function withExists($relation) {}
    /** @return $this */
    protected function addHasWhere(\Illuminate\Database\Eloquent\Builder $hasQuery, \Illuminate\Database\Eloquent\Relations\Relation $relation, $operator, $count, $boolean) {}
    /** @return $this */
    public function mergeConstraintsFrom(\Illuminate\Database\Eloquent\Builder $from) {}
    protected function requalifyWhereTables(array $wheres, string $from, string $to): array {}
    /** @return $this */
    protected function addWhereCountQuery(\Illuminate\Database\Query\Builder $query, $operator = '>=', $count = 1, $boolean = 'and') {}
    /** @return \Illuminate\Database\Eloquent\Relations\Relation<*, */
    protected function getRelationWithoutConstraints($relation) {}
    /** @return bool */
    protected function canUseExistsForExistenceCheck($operator, $count) {}
}
