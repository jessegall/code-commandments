<?php

namespace Illuminate\Database\Eloquent;

trait HasCollection
{
    protected static array $resolvedCollectionClasses = array (
);
    public function newCollection(array $models = array (
)) {}
    public function resolveCollectionFromAttribute() {}
}
