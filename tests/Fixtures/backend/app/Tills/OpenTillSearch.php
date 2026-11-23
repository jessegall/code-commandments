<?php

namespace Shop\Tills;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The search the till screen opens with: only tills open at the store the request names.
 */
final class OpenTillSearch
{
    public readonly Closure $filter;

    #[Sinful(ContainerReach::class)]
    public function __construct()
    {
        $this->filter = static fn (Builder $builder) => $builder->where('store', app('request')->input('store'));
    }
}
