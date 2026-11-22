<?php

namespace Illuminate\Database\Eloquent\Relations;

abstract class Relation implements \Illuminate\Contracts\Database\Eloquent\Builder, \Illuminate\Contracts\Database\Query\Builder
{
    use \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Support\Traits\Macroable;
    protected $query = NULL;
    protected $parent = NULL;
    protected $related = NULL;
    protected $eagerKeysWereEmpty = false;
    protected static $constraints = true;
    public static $morphMap = array (
);
    protected static $requireMorphMap = false;
    protected static $selfJoinCount = 0;
    public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent) {}
    public static function noConstraints(\Closure $callback) {}
    abstract public function addConstraints();
    abstract public function addEagerConstraints(array $models);
    abstract public function initRelation(array $models, $relation);
    abstract public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation);
    abstract public function getResults();
    public function getEager() {}
    public function sole($columns = array (
  0 => '*',
)) {}
    public function get($columns = array (
  0 => '*',
)) {}
    public function touch() {}
    public function rawUpdate(array $attributes = array (
)) {}
    public function getRelationExistenceCountQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery) {}
    public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    public function getRelationCountHash($incrementJoinCount = true) {}
    protected function getKeys(array $models, $key = NULL) {}
    protected function getRelationQuery() {}
    public function getQuery() {}
    public function getBaseQuery() {}
    public function toBase() {}
    public function getParent() {}
    public function getQualifiedParentKeyName() {}
    public function getRelated() {}
    public function createdAt() {}
    public function updatedAt() {}
    public function relatedUpdatedAt() {}
    protected function whereInEager(string $whereIn, string $key, array $modelKeys, ?\Illuminate\Database\Eloquent\Builder $query = NULL) {}
    protected function whereInMethod(\Illuminate\Database\Eloquent\Model $model, $key) {}
    public static function requireMorphMap($requireMorphMap = true) {}
    public static function requiresMorphMap() {}
    public static function enforceMorphMap(array $map, $merge = true) {}
    public static function morphMap(?array $map = NULL, $merge = true) {}
    protected static function buildMorphMapFromModels(?array $models = NULL) {}
    public static function getMorphedModel($alias) {}
    public static function getMorphAlias(string $className) {}
    public function __call($method, $parameters) {}
    public function __clone() {}
    public function macroCall($method, $parameters) {}
}
