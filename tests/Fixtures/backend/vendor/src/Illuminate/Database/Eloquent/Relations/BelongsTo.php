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
    /** @return void */
    public function addConstraints() {}
    public function addEagerConstraints(array $models) {}
    /** @return array */
    protected function getEagerModelKeys(array $models) {}
    public function initRelation(array $models, $relation) {}
    public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
    /** @return TDeclaringModel */
    public function associate($model) {}
    /** @return TDeclaringModel */
    public function dissociate() {}
    /** @return TDeclaringModel */
    public function disassociate() {}
    /** @return void */
    public function touch() {}
    public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    /** @return \Illuminate\Database\Eloquent\Builder<TRelatedModel> */
    public function getRelationExistenceQueryForSelfRelation(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    /** @return bool */
    protected function relationHasIncrementingId() {}
    /** @return TRelatedModel */
    protected function newRelatedInstanceFor(\Illuminate\Database\Eloquent\Model $parent) {}
    /** @return TDeclaringModel */
    public function getChild() {}
    /** @return string */
    public function getForeignKeyName() {}
    /** @return string */
    public function getQualifiedForeignKeyName() {}
    /** @return mixed */
    public function getParentKey() {}
    /** @return string */
    public function getOwnerKeyName() {}
    /** @return string */
    public function getQualifiedOwnerKeyName() {}
    /** @return int|string */
    protected function getRelatedKeyFrom(\Illuminate\Database\Eloquent\Model $model) {}
    /** @return mixed */
    protected function getForeignKeyFrom(\Illuminate\Database\Eloquent\Model $model) {}
    /** @return string */
    public function getRelationName() {}
}
