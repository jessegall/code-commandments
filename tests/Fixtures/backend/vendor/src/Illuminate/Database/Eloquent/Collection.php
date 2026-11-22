<?php

namespace Illuminate\Database\Eloquent;

class Collection extends \Illuminate\Support\Collection implements \Illuminate\Contracts\Queue\QueueableCollection
{
    use \Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithDictionary;
    /** @return ($key */
    public function find($key, $default = NULL) {}
    /** @return TModel */
    public function findOrFail($key) {}
    /** @return $this */
    public function load($relations) {}
    /** @return $this */
    public function loadAggregate($relations, $column, $function = NULL) {}
    /** @return $this */
    public function loadCount($relations) {}
    /** @return $this */
    public function loadMax($relations, $column) {}
    /** @return $this */
    public function loadMin($relations, $column) {}
    /** @return $this */
    public function loadSum($relations, $column) {}
    /** @return $this */
    public function loadAvg($relations, $column) {}
    /** @return $this */
    public function loadExists($relations) {}
    /** @return $this */
    public function loadMissing($relations) {}
    /** @return void */
    public function loadMissingRelationshipChain(array $tuples) {}
    /** @return void */
    protected function loadMissingRelation(\Illuminate\Database\Eloquent\Collection $models, array $path) {}
    /** @return $this */
    public function loadMorph($relation, $relations) {}
    /** @return $this */
    public function loadMorphCount($relation, $relations) {}
    /** @return bool */
    public function contains($key, $operator = NULL, $value = NULL) {}
    /** @return bool */
    public function doesntContain($key, $operator = NULL, $value = NULL) {}
    /** @return array<int, */
    public function modelKeys() {}
    /** @return static */
    public function merge($items) {}
    /** @return \Illuminate\Support\Collection<TKey, */
    public function map(callable $callback) {}
    /** @return \Illuminate\Support\Collection<TMapWithKeysKey, */
    public function mapWithKeys(callable $callback) {}
    /** @return static */
    public function fresh($with = array (
)) {}
    /** @return static */
    public function diff($items) {}
    /** @return static */
    public function intersect($items) {}
    /** @return static */
    public function unique($key = NULL, $strict = false) {}
    /** @return static */
    public function only($keys) {}
    /** @return static */
    public function except($keys) {}
    /** @return $this */
    public function makeHidden($attributes) {}
    /** @return $this */
    public function mergeHidden($attributes) {}
    /** @return $this */
    public function setHidden($hidden) {}
    /** @return $this */
    public function makeVisible($attributes) {}
    /** @return $this */
    public function mergeVisible($attributes) {}
    /** @return $this */
    public function setVisible($visible) {}
    /** @return $this */
    public function append($attributes) {}
    /** @return $this */
    public function setAppends(array $appends) {}
    /** @return $this */
    public function withoutAppends() {}
    /** @return array<array-key, */
    public function getDictionary($items = NULL) {}
    /** @return \Illuminate\Support\Collection<array-key, */
    public function countBy($countBy = NULL) {}
    /** @return \Illuminate\Support\Collection<int, */
    public function collapse() {}
    /** @return \Illuminate\Support\Collection<int, */
    public function flatten($depth = \Illuminate\Database\Eloquent\INF) {}
    /** @return \Illuminate\Support\Collection<TModel, */
    public function flip() {}
    /** @return \Illuminate\Support\Collection<int, */
    public function keys() {}
    /** @return \Illuminate\Support\Collection<int, */
    public function pad($size, $value) {}
    /** @return \Illuminate\Support\Collection<int<0, */
    public function partition($key, $operator = NULL, $value = NULL) {}
    /** @return \Illuminate\Support\Collection<array-key, */
    public function pluck($value, $key = NULL) {}
    /** @return \Illuminate\Support\Collection<int, */
    public function zip($items) {}
    /** @return callable(TModel, */
    protected function duplicateComparator($strict) {}
    /** @return $this */
    public function withRelationshipAutoloading() {}
    /** @return string|null */
    public function getQueueableClass() {}
    /** @return string */
    protected function getQueueableModelClass($model) {}
    /** @return array<int, */
    public function getQueueableIds() {}
    /** @return array<int, */
    public function getQueueableRelations() {}
    /** @return string|null */
    public function getQueueableConnection() {}
    /** @return \Illuminate\Database\Eloquent\Builder<TModel> */
    public function toQuery() {}
}
