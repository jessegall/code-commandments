<?php

namespace Illuminate\Database\Eloquent\Relations;

class MorphTo extends \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    use \Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithDictionary;
    protected $morphType = NULL;
    protected $ownerKey = NULL;
    protected $models = NULL;
    protected $dictionary = array (
);
    protected $macroBuffer = array (
);
    protected $morphableEagerLoads = array (
);
    protected $morphableEagerLoadCounts = array (
);
    protected $morphableConstraints = array (
);
    public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $ownerKey, $type, $relation) {}
    public function addEagerConstraints(array $models) {}
    /** @return void */
    protected function buildDictionary(\Illuminate\Database\Eloquent\Collection $models) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function getEager() {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    protected function getResultsByType($type) {}
    /** @return array */
    protected function gatherKeysByType($type, $keyType) {}
    /** @return TRelatedModel */
    public function createModelByType($type) {}
    public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
    /** @return void */
    protected function matchToMorphParents($type, \Illuminate\Database\Eloquent\Collection $results) {}
    /** @return TDeclaringModel */
    public function associate($model) {}
    /** @return TDeclaringModel */
    public function dissociate() {}
    public function touch() {}
    protected function newRelatedInstanceFor(\Illuminate\Database\Eloquent\Model $parent) {}
    /** @return string */
    public function getMorphType() {}
    /** @return array */
    public function getDictionary() {}
    /** @return $this */
    public function morphWith(array $with) {}
    /** @return $this */
    public function morphWithCount(array $withCount) {}
    /** @return $this */
    public function constrain(array $callbacks) {}
    /** @return $this */
    public function withTrashed() {}
    /** @return $this */
    public function withoutTrashed() {}
    /** @return $this */
    public function onlyTrashed() {}
    /** @return \Illuminate\Database\Eloquent\Builder<TRelatedModel> */
    protected function replayMacros(\Illuminate\Database\Eloquent\Builder $query) {}
    public function getQualifiedOwnerKeyName() {}
    /** @return mixed */
    public function __call($method, $parameters) {}
}
