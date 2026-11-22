<?php

namespace Illuminate\Database\Eloquent;

interface Scope
{
    /** @return void */
    public function apply(\Illuminate\Database\Eloquent\Builder $builder, \Illuminate\Database\Eloquent\Model $model);
}
