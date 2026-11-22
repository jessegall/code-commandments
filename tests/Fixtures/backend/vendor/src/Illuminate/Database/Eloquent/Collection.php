<?php

namespace Illuminate\Database\Eloquent;

class Collection extends \Illuminate\Support\Collection implements \Illuminate\Contracts\Queue\QueueableCollection
{
    use \Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithDictionary;
    public function find($key, $default = NULL) {}
    public function findOrFail($key) {}
    public function load($relations) {}
    public function loadAggregate($relations, $column, $function = NULL) {}
    public function loadCount($relations) {}
    public function loadMax($relations, $column) {}
    public function loadMin($relations, $column) {}
    public function loadSum($relations, $column) {}
    public function loadAvg($relations, $column) {}
    public function loadExists($relations) {}
    public function loadMissing($relations) {}
    public function loadMissingRelationshipChain(array $tuples) {}
    protected function loadMissingRelation(\Illuminate\Database\Eloquent\Collection $models, array $path) {}
    public function loadMorph($relation, $relations) {}
    public function loadMorphCount($relation, $relations) {}
    public function contains($key, $operator = NULL, $value = NULL) {}
    public function doesntContain($key, $operator = NULL, $value = NULL) {}
    public function modelKeys() {}
    public function merge($items) {}
    public function map(callable $callback) {}
    public function mapWithKeys(callable $callback) {}
    public function fresh($with = array (
)) {}
    public function diff($items) {}
    public function intersect($items) {}
    public function unique($key = NULL, $strict = false) {}
    public function only($keys) {}
    public function except($keys) {}
    public function makeHidden($attributes) {}
    public function mergeHidden($attributes) {}
    public function setHidden($hidden) {}
    public function makeVisible($attributes) {}
    public function mergeVisible($attributes) {}
    public function setVisible($visible) {}
    public function append($attributes) {}
    public function setAppends(array $appends) {}
    public function withoutAppends() {}
    public function getDictionary($items = NULL) {}
    public function countBy($countBy = NULL) {}
    public function collapse() {}
    public function flatten($depth = \Illuminate\Database\Eloquent\INF) {}
    public function flip() {}
    public function keys() {}
    public function pad($size, $value) {}
    public function partition($key, $operator = NULL, $value = NULL) {}
    public function pluck($value, $key = NULL) {}
    public function zip($items) {}
    protected function duplicateComparator($strict) {}
    public function withRelationshipAutoloading() {}
    public function getQueueableClass() {}
    protected function getQueueableModelClass($model) {}
    public function getQueueableIds() {}
    public function getQueueableRelations() {}
    public function getQueueableConnection() {}
    public function toQuery() {}
}
