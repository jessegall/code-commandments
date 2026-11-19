<?php

namespace Shop\Reporting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\BareScopeEscape;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The subscriptions that ended inside a reporting window, read from a query the caller narrowed.
 */
final class ChurnedSubscriptions
{
    #[Sinful(BareScopeEscape::class)]
    public function endedBetween(Builder $subscriptions, string $from, string $until): Collection
    {
        return $subscriptions
            ->withoutGlobalScopes()
            ->whereBetween('ended_at', [$from, $until])
            ->get();
    }
}
