<?php

namespace Illuminate\Database\Eloquent\Relations;

class BelongsTo extends \Illuminate\Database\Eloquent\Relations\Relation
{
    use \Illuminate\Database\Eloquent\Relations\Concerns\ComparesRelatedModels, \Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithDictionary, \Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;
    protected $child = NULL;
    protected $foreignKey = NULL;
    protected $ownerKey = NULL;
    protected $relationName = NULL;
    public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $child, $foreignKey, $ownerKey, $relationName) {}
    public function getResults() {}
    public function addConstraints() {}
    public function addEagerConstraints(array $models) {}
    protected function getEagerModelKeys(array $models) {}
    public function initRelation(array $models, $relation) {}
    public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
    public function associate($model) {}
    public function dissociate() {}
    public function disassociate() {}
    public function touch() {}
    public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    public function getRelationExistenceQueryForSelfRelation(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    protected function relationHasIncrementingId() {}
    protected function newRelatedInstanceFor(\Illuminate\Database\Eloquent\Model $parent) {}
    public function getChild() {}
    public function getForeignKeyName() {}
    public function getQualifiedForeignKeyName() {}
    public function getParentKey() {}
    public function getOwnerKeyName() {}
    public function getQualifiedOwnerKeyName() {}
    protected function getRelatedKeyFrom(\Illuminate\Database\Eloquent\Model $model) {}
    protected function getForeignKeyFrom(\Illuminate\Database\Eloquent\Model $model) {}
    public function getRelationName() {}
}
