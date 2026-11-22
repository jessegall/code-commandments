<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HasRelationships
{
    protected $relations = array (
);
    protected $touches = array (
);
    protected $relationAutoloadCallback = NULL;
    protected $relationAutoloadContext = NULL;
    public static $manyMethods = array (
  0 => 'belongsToMany',
  1 => 'morphToMany',
  2 => 'morphedByMany',
);
    protected static $relationResolvers = array (
);
    public function relationResolver($class, $key) {}
    public static function resolveRelationUsing($name, \Closure $callback) {}
    public function hasRelationAutoloadCallback() {}
    public function autoloadRelationsUsing(\Closure $callback, $context = NULL) {}
    protected function attemptToAutoloadRelation($key) {}
    protected function invokeRelationAutoloadCallbackFor($key, $tuples) {}
    protected function propagateRelationAutoloadCallbackToRelation($key, $models) {}
    public function hasOne($related, $foreignKey = NULL, $localKey = NULL) {}
    protected function newHasOne(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $localKey) {}
    public function hasOneThrough($related, $through, $firstKey = NULL, $secondKey = NULL, $localKey = NULL, $secondLocalKey = NULL) {}
    protected function newHasOneThrough(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $farParent, \Illuminate\Database\Eloquent\Model $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey) {}
    public function morphOne($related, $name, $type = NULL, $id = NULL, $localKey = NULL) {}
    protected function newMorphOne(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $type, $id, $localKey) {}
    public function belongsTo($related, $foreignKey = NULL, $ownerKey = NULL, $relation = NULL) {}
    protected function newBelongsTo(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $child, $foreignKey, $ownerKey, $relation) {}
    public function morphTo($name = NULL, $type = NULL, $id = NULL, $ownerKey = NULL) {}
    protected function morphEagerTo($name, $type, $id, $ownerKey) {}
    protected function morphInstanceTo($target, $name, $type, $id, $ownerKey) {}
    protected function newMorphTo(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $ownerKey, $type, $relation) {}
    public static function getActualClassNameForMorph($class) {}
    protected function guessBelongsToRelation() {}
    public function through($relationship) {}
    public function hasMany($related, $foreignKey = NULL, $localKey = NULL) {}
    protected function newHasMany(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $localKey) {}
    public function hasManyThrough($related, $through, $firstKey = NULL, $secondKey = NULL, $localKey = NULL, $secondLocalKey = NULL) {}
    protected function newHasManyThrough(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $farParent, \Illuminate\Database\Eloquent\Model $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey) {}
    public function morphMany($related, $name, $type = NULL, $id = NULL, $localKey = NULL) {}
    protected function newMorphMany(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $type, $id, $localKey) {}
    public function belongsToMany($related, $table = NULL, $foreignPivotKey = NULL, $relatedPivotKey = NULL, $parentKey = NULL, $relatedKey = NULL, $relation = NULL) {}
    protected function newBelongsToMany(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = NULL) {}
    public function morphToMany($related, $name, $table = NULL, $foreignPivotKey = NULL, $relatedPivotKey = NULL, $parentKey = NULL, $relatedKey = NULL, $relation = NULL, $inverse = false) {}
    protected function newMorphToMany(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = NULL, $inverse = false) {}
    public function morphedByMany($related, $name, $table = NULL, $foreignPivotKey = NULL, $relatedPivotKey = NULL, $parentKey = NULL, $relatedKey = NULL, $relation = NULL) {}
    protected function guessBelongsToManyRelation() {}
    public function joiningTable($related, $instance = NULL) {}
    public function joiningTableSegment() {}
    public function touches($relation) {}
    public function touchOwners() {}
    protected function getMorphs($name, $type, $id) {}
    public function getMorphClass() {}
    protected function newRelatedInstance($class) {}
    protected function newRelatedThroughInstance($class) {}
    public function getRelations() {}
    public function getRelation($relation) {}
    public function relationLoaded($key) {}
    public function setRelation($relation, $value) {}
    public function unsetRelation($relation) {}
    public function setRelations(array $relations) {}
    public function withRelationshipAutoloading() {}
    public function withoutRelations() {}
    public function withoutRelation($relations) {}
    public function unsetRelations() {}
    public function getTouchedRelations() {}
    public function setTouchedRelations(array $touches) {}
}
