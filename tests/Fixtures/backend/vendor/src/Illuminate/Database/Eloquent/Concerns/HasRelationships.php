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
    /** @return Closure|null */
    public function relationResolver($class, $key) {}
    /** @return void */
    public static function resolveRelationUsing($name, \Closure $callback) {}
    /** @return bool */
    public function hasRelationAutoloadCallback() {}
    /** @return $this */
    public function autoloadRelationsUsing(\Closure $callback, $context = NULL) {}
    /** @return bool */
    protected function attemptToAutoloadRelation($key) {}
    /** @return void */
    protected function invokeRelationAutoloadCallbackFor($key, $tuples) {}
    /** @return void */
    protected function propagateRelationAutoloadCallbackToRelation($key, $models) {}
    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<TRelatedModel, */
    public function hasOne($related, $foreignKey = NULL, $localKey = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<TRelatedModel, */
    protected function newHasOne(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $localKey) {}
    /** @return \Illuminate\Database\Eloquent\Relations\HasOneThrough<TRelatedModel, */
    public function hasOneThrough($related, $through, $firstKey = NULL, $secondKey = NULL, $localKey = NULL, $secondLocalKey = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\HasOneThrough<TRelatedModel, */
    protected function newHasOneThrough(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $farParent, \Illuminate\Database\Eloquent\Model $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphOne<TRelatedModel, */
    public function morphOne($related, $name, $type = NULL, $id = NULL, $localKey = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphOne<TRelatedModel, */
    protected function newMorphOne(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $type, $id, $localKey) {}
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<TRelatedModel, */
    public function belongsTo($related, $foreignKey = NULL, $ownerKey = NULL, $relation = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<TRelatedModel, */
    protected function newBelongsTo(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $child, $foreignKey, $ownerKey, $relation) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphTo<\Illuminate\Database\Eloquent\Model, */
    public function morphTo($name = NULL, $type = NULL, $id = NULL, $ownerKey = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphTo<\Illuminate\Database\Eloquent\Model, */
    protected function morphEagerTo($name, $type, $id, $ownerKey) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphTo<\Illuminate\Database\Eloquent\Model, */
    protected function morphInstanceTo($target, $name, $type, $id, $ownerKey) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, */
    protected function newMorphTo(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $ownerKey, $type, $relation) {}
    /** @return string */
    public static function getActualClassNameForMorph($class) {}
    /** @return string */
    protected function guessBelongsToRelation() {}
    /** @return ( */
    public function through($relationship) {}
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<TRelatedModel, */
    public function hasMany($related, $foreignKey = NULL, $localKey = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<TRelatedModel, */
    protected function newHasMany(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $localKey) {}
    /** @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<TRelatedModel, */
    public function hasManyThrough($related, $through, $firstKey = NULL, $secondKey = NULL, $localKey = NULL, $secondLocalKey = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<TRelatedModel, */
    protected function newHasManyThrough(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $farParent, \Illuminate\Database\Eloquent\Model $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<TRelatedModel, */
    public function morphMany($related, $name, $type = NULL, $id = NULL, $localKey = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<TRelatedModel, */
    protected function newMorphMany(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $type, $id, $localKey) {}
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<TRelatedModel, */
    public function belongsToMany($related, $table = NULL, $foreignPivotKey = NULL, $relatedPivotKey = NULL, $parentKey = NULL, $relatedKey = NULL, $relation = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<TRelatedModel, */
    protected function newBelongsToMany(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphToMany<TRelatedModel, */
    public function morphToMany($related, $name, $table = NULL, $foreignPivotKey = NULL, $relatedPivotKey = NULL, $parentKey = NULL, $relatedKey = NULL, $relation = NULL, $inverse = false) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphToMany<TRelatedModel, */
    protected function newMorphToMany(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = NULL, $inverse = false) {}
    /** @return \Illuminate\Database\Eloquent\Relations\MorphToMany<TRelatedModel, */
    public function morphedByMany($related, $name, $table = NULL, $foreignPivotKey = NULL, $relatedPivotKey = NULL, $parentKey = NULL, $relatedKey = NULL, $relation = NULL) {}
    /** @return string|null */
    protected function guessBelongsToManyRelation() {}
    /** @return string */
    public function joiningTable($related, $instance = NULL) {}
    /** @return string */
    public function joiningTableSegment() {}
    /** @return bool */
    public function touches($relation) {}
    /** @return void */
    public function touchOwners() {}
    /** @return array */
    protected function getMorphs($name, $type, $id) {}
    /** @return string */
    public function getMorphClass() {}
    /** @return TRelatedModel */
    protected function newRelatedInstance($class) {}
    /** @return TRelatedModel */
    protected function newRelatedThroughInstance($class) {}
    /** @return array */
    public function getRelations() {}
    /** @return mixed */
    public function getRelation($relation) {}
    /** @return bool */
    public function relationLoaded($key) {}
    /** @return $this */
    public function setRelation($relation, $value) {}
    /** @return $this */
    public function unsetRelation($relation) {}
    /** @return $this */
    public function setRelations(array $relations) {}
    /** @return $this */
    public function withRelationshipAutoloading() {}
    /** @return $this */
    public function withoutRelations() {}
    /** @return $this */
    public function withoutRelation($relations) {}
    /** @return $this */
    public function unsetRelations() {}
    /** @return array */
    public function getTouchedRelations() {}
    /** @return $this */
    public function setTouchedRelations(array $touches) {}
}
