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
    /** @return TReturn */
    public static function noConstraints(\Closure $callback) {}
    /** @return void */
    abstract public function addConstraints();
    /** @return void */
    abstract public function addEagerConstraints(array $models);
    /** @return array<int, */
    abstract public function initRelation(array $models, $relation);
    /** @return array<int, */
    abstract public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation);
    /** @return TResult */
    abstract public function getResults();
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function getEager() {}
    /** @return TRelatedModel */
    public function sole($columns = array (
  0 => '*',
)) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function get($columns = array (
  0 => '*',
)) {}
    /** @return void */
    public function touch() {}
    /** @return int */
    public function rawUpdate(array $attributes = array (
)) {}
    /** @return \Illuminate\Database\Eloquent\Builder<TRelatedModel> */
    public function getRelationExistenceCountQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery) {}
    /** @return \Illuminate\Database\Eloquent\Builder<TRelatedModel> */
    public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array (
  0 => '*',
)) {}
    /** @return string */
    public function getRelationCountHash($incrementJoinCount = true) {}
    /** @return array<int, */
    protected function getKeys(array $models, $key = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Builder<TRelatedModel> */
    protected function getRelationQuery() {}
    /** @return \Illuminate\Database\Eloquent\Builder<TRelatedModel> */
    public function getQuery() {}
    /** @return \Illuminate\Database\Query\Builder */
    public function getBaseQuery() {}
    /** @return \Illuminate\Database\Query\Builder */
    public function toBase() {}
    /** @return TDeclaringModel */
    public function getParent() {}
    /** @return string */
    public function getQualifiedParentKeyName() {}
    /** @return TRelatedModel */
    public function getRelated() {}
    /** @return string */
    public function createdAt() {}
    /** @return string */
    public function updatedAt() {}
    /** @return string */
    public function relatedUpdatedAt() {}
    /** @return void */
    protected function whereInEager(string $whereIn, string $key, array $modelKeys, ?\Illuminate\Database\Eloquent\Builder $query = NULL) {}
    /** @return string */
    protected function whereInMethod(\Illuminate\Database\Eloquent\Model $model, $key) {}
    /** @return void */
    public static function requireMorphMap($requireMorphMap = true) {}
    /** @return bool */
    public static function requiresMorphMap() {}
    /** @return array */
    public static function enforceMorphMap(array $map, $merge = true) {}
    /** @return array<string, */
    public static function morphMap(?array $map = NULL, $merge = true) {}
    /** @return array<string, */
    protected static function buildMorphMapFromModels(?array $models = NULL) {}
    /** @return class-string<\Illuminate\Database\Eloquent\Model>|null */
    public static function getMorphedModel($alias) {}
    /** @return int|string */
    public static function getMorphAlias(string $className) {}
    /** @return mixed */
    public function __call($method, $parameters) {}
    /** @return void */
    public function __clone() {}
    /** @return mixed */
    public function macroCall($method, $parameters) {}
}
