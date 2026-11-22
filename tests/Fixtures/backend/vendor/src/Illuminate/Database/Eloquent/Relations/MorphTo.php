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
    protected function buildDictionary(\Illuminate\Database\Eloquent\Collection $models) {}
    public function getEager() {}
    protected function getResultsByType($type) {}
    protected function gatherKeysByType($type, $keyType) {}
    public function createModelByType($type) {}
    public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
    protected function matchToMorphParents($type, \Illuminate\Database\Eloquent\Collection $results) {}
    public function associate($model) {}
    public function dissociate() {}
    public function touch() {}
    protected function newRelatedInstanceFor(\Illuminate\Database\Eloquent\Model $parent) {}
    public function getMorphType() {}
    public function getDictionary() {}
    public function morphWith(array $with) {}
    public function morphWithCount(array $withCount) {}
    public function constrain(array $callbacks) {}
    public function withTrashed() {}
    public function withoutTrashed() {}
    public function onlyTrashed() {}
    protected function replayMacros(\Illuminate\Database\Eloquent\Builder $query) {}
    public function getQualifiedOwnerKeyName() {}
    public function __call($method, $parameters) {}
}
