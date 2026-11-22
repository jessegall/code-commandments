<?php

namespace Illuminate\Database\Eloquent;

trait HasCollection
{
    protected static array $resolvedCollectionClasses = array (
);
    /** @return TCollection */
    public function newCollection(array $models = array (
)) {}
    /** @return class-string<TCollection>|null */
    public function resolveCollectionFromAttribute() {}
}
