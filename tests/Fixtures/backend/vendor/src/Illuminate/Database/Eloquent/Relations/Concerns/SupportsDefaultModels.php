<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

trait SupportsDefaultModels
{
    protected $withDefault = NULL;
    /** @return \Illuminate\Database\Eloquent\Model */
    abstract protected function newRelatedInstanceFor(\Illuminate\Database\Eloquent\Model $parent);
    /** @return $this */
    public function withDefault($callback = true) {}
    /** @return \Illuminate\Database\Eloquent\Model|null */
    protected function getDefaultFor(\Illuminate\Database\Eloquent\Model $parent) {}
}
