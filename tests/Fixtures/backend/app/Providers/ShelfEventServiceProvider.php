<?php

namespace Shop\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadEventWiring;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Events\ShelfRestocked;
use Shop\Listeners\NotifyShelfStaff;

/**
 * Wires the shelf listeners through the provider's listen map, for an event nothing raises any more.
 */
#[Sinful(DeadEventWiring::class)]
final class ShelfEventServiceProvider extends EventServiceProvider
{
    protected $listen = [
        ShelfRestocked::class => [NotifyShelfStaff::class],
    ];
}
