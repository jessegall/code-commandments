<?php

namespace Illuminate\Database\Eloquent\Relations;

class HasMany extends \Illuminate\Database\Eloquent\Relations\HasOneOrMany
{
    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<TRelatedModel, */
    public function one() {}
    public function getResults() {}
    public function initRelation(array $models, $relation) {}
    public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
}
