<?php

namespace Illuminate\Database\Eloquent;

interface Scope
{
    public function apply(\Illuminate\Database\Eloquent\Builder $builder, \Illuminate\Database\Eloquent\Model $model);
}
