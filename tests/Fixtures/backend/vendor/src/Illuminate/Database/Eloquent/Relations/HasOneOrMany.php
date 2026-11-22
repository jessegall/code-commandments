<?php

namespace Illuminate\Database\Eloquent\Relations;

abstract class HasOneOrMany extends \Illuminate\Database\Eloquent\Relations\Relation
{
    use \Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithDictionary, \Illuminate\Database\Eloquent\Relations\Concerns\SupportsInverseRelations;
    protected $foreignKey = NULL;
    protected $localKey = NULL;
    public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $localKey) {}
    /** @return TRelatedModel */
    public function make(array $attributes = array (
)) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function makeMany($records) {}
    /** @return void */
    public function addConstraints() {}
    public function addEagerConstraints(array $models) {}
    /** @return array<int, */
    public function matchOne(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
    /** @return array<int, */
    public function matchMany(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
    /** @return array<int, */
    protected function matchOneOrMany(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation, $type) {}
    /** @return mixed */
    protected function getRelationValue(array $dictionary, $key, $type) {}
    /** @return array<array<array-key, */
    protected function buildDictionary(\Illuminate\Database\Eloquent\Collection $results) {}
    /** @return ($id */
    public function findOrNew($id, $columns = array (
  0 => '*',
)) {}
    /** @return TRelatedModel */
    public function firstOrNew(array $attributes = array (
), array $values = array (
)) {}
    /** @return TRelatedModel */
    public function firstOrCreate(array $attributes = array (
), \Closure|array $values = array (
)) {}
    /** @return TRelatedModel */
    public function createOrFirst(array $attributes = array (
), \Closure|array $values = array (
)) {}
    /** @return TRelatedModel */
    public function updateOrCreate(array $attributes, array $values = array (
)) {}
    /** @return int */
    public function upsert(array $values, $uniqueBy, $update = NULL) {}
    /** @return TRelatedModel|false */
    public function save(\Illuminate\Database\Eloquent\Model $model) {}
    /** @return TRelatedModel|false */
    public function saveQuietly(\Illuminate\Database\Eloquent\Model $model) {}
    /** @return iterable<TRelatedModel> */
    public function saveMany($models) {}
    /** @return iterable<TRelatedModel> */
    public function saveManyQuietly($models) {}
    /** @return TRelatedModel */
    public function create(array $attributes = array (
)) {}
    /** @return TRelatedModel */
    public function createQuietly(array $attributes = array (
)) {}
    /** @return TRelatedModel */
    public function forceCreate(array $attributes = array (
)) {}
    /** @return TRelatedModel */
    public function forceCreateQuietly(array $attributes = array (
)) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function createMany(iterable $records) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function createManyQuietly(iterable $records) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function forceCreateMany(iterable $records) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function forceCreateManyQuietly(iterable $records) {}
    /** @return void */
    protected function setForeignAttributesForCreate(\Illuminate\Database\Eloquent\Model $model) {}
    public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    /** @return \Illuminate\Database\Eloquent\Builder<TRelatedModel> */
    public function getRelationExistenceQueryForSelfRelation(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    /** @return $this */
    public function take($value) {}
    /** @return $this */
    public function limit($value) {}
    /** @return string */
    public function getExistenceCompareKey() {}
    /** @return mixed */
    public function getParentKey() {}
    /** @return string */
    public function getQualifiedParentKeyName() {}
    /** @return string */
    public function getForeignKeyName() {}
    /** @return string */
    public function getQualifiedForeignKeyName() {}
    /** @return string */
    public function getLocalKeyName() {}
}
