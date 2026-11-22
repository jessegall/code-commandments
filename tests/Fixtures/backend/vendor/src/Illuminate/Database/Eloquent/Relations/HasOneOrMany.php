<?php

namespace Illuminate\Database\Eloquent\Relations;

abstract class HasOneOrMany extends \Illuminate\Database\Eloquent\Relations\Relation
{
    use \Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithDictionary, \Illuminate\Database\Eloquent\Relations\Concerns\SupportsInverseRelations;
    protected $foreignKey = NULL;
    protected $localKey = NULL;
    public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $localKey) {}
    public function make(array $attributes = array (
)) {}
    public function makeMany($records) {}
    public function addConstraints() {}
    public function addEagerConstraints(array $models) {}
    public function matchOne(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
    public function matchMany(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
    protected function matchOneOrMany(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation, $type) {}
    protected function getRelationValue(array $dictionary, $key, $type) {}
    protected function buildDictionary(\Illuminate\Database\Eloquent\Collection $results) {}
    public function findOrNew($id, $columns = array (
  0 => '*',
)) {}
    public function firstOrNew(array $attributes = array (
), array $values = array (
)) {}
    public function firstOrCreate(array $attributes = array (
), \Closure|array $values = array (
)) {}
    public function createOrFirst(array $attributes = array (
), \Closure|array $values = array (
)) {}
    public function updateOrCreate(array $attributes, array $values = array (
)) {}
    public function upsert(array $values, $uniqueBy, $update = NULL) {}
    public function save(\Illuminate\Database\Eloquent\Model $model) {}
    public function saveQuietly(\Illuminate\Database\Eloquent\Model $model) {}
    public function saveMany($models) {}
    public function saveManyQuietly($models) {}
    public function create(array $attributes = array (
)) {}
    public function createQuietly(array $attributes = array (
)) {}
    public function forceCreate(array $attributes = array (
)) {}
    public function forceCreateQuietly(array $attributes = array (
)) {}
    public function createMany(iterable $records) {}
    public function createManyQuietly(iterable $records) {}
    public function forceCreateMany(iterable $records) {}
    public function forceCreateManyQuietly(iterable $records) {}
    protected function setForeignAttributesForCreate(\Illuminate\Database\Eloquent\Model $model) {}
    public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    public function getRelationExistenceQueryForSelfRelation(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    public function take($value) {}
    public function limit($value) {}
    public function getExistenceCompareKey() {}
    public function getParentKey() {}
    public function getQualifiedParentKeyName() {}
    public function getForeignKeyName() {}
    public function getQualifiedForeignKeyName() {}
    public function getLocalKeyName() {}
}
