<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

trait ComparesRelatedModels
{
    /** @return bool */
    public function is($model) {}
    /** @return bool */
    public function isNot($model) {}
    /** @return mixed */
    abstract public function getParentKey();
    /** @return mixed */
    abstract protected function getRelatedKeyFrom(\Illuminate\Database\Eloquent\Model $model);
    /** @return bool */
    protected function compareKeys($parentKey, $relatedKey) {}
}
