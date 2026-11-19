<?php

namespace Shop\Models\Concerns;

use Illuminate\Container\Container;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Tills\OpenTill;

/**
 * Limits a model's queries to the open till; the till is looked up when a query runs, never when the scope is made.
 */
trait BelongsToOpenTill
{
    #[Righteous(ContainerReach::class)]
    public static function bootBelongsToOpenTill(): void
    {
        static::addGlobalScope('open-till', static fn ($query) => $query->where('till_number', Container::getInstance()->make(OpenTill::class)->number()));
    }
}
