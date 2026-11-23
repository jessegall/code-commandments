<?php

namespace Shop\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Tills\OpenTill;

/**
 * Limits a query to the rows of the till that is open. The framework builds a scope with new, so it reaches the
 * container for the till itself.
 */
final class OpenTillScope implements Scope
{
    #[Righteous(ContainerReach::class)]
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('till_number', app(OpenTill::class)->number());
    }
}
