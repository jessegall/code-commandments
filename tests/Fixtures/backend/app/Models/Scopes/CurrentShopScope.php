<?php

namespace Shop\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits a query to the rows of the shop the request is served for.
 */
final class CurrentShopScope implements Scope
{
    public function __construct(private readonly int $shopId) {}

    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('shop_id', $this->shopId);
    }
}
