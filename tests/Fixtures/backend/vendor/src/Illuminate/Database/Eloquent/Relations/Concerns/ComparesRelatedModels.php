<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

trait ComparesRelatedModels
{
    public function is($model) {}
    public function isNot($model) {}
    abstract public function getParentKey();
    abstract protected function getRelatedKeyFrom(\Illuminate\Database\Eloquent\Model $model);
    protected function compareKeys($parentKey, $relatedKey) {}
}
