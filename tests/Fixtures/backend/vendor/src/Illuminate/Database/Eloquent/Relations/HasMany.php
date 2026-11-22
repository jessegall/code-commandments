<?php

namespace Illuminate\Database\Eloquent\Relations;

class HasMany extends \Illuminate\Database\Eloquent\Relations\HasOneOrMany
{
    public function one() {}
    public function getResults() {}
    public function initRelation(array $models, $relation) {}
    public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation) {}
}
