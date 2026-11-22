<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

trait SupportsDefaultModels
{
    protected $withDefault = NULL;
    abstract protected function newRelatedInstanceFor(\Illuminate\Database\Eloquent\Model $parent);
    public function withDefault($callback = true) {}
    protected function getDefaultFor(\Illuminate\Database\Eloquent\Model $parent) {}
}
