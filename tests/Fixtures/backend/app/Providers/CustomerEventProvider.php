<?php

namespace Shop\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadEventWiring;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Events\CustomerMerged;

/**
 * Several listeners stacked on one event, which is what makes this the hardest kind to spot: the
 * stack reads as an important integration point. The merge tool that raised CustomerMerged was
 * removed last quarter, so not one of these can run.
 */
class CustomerEventProvider extends ServiceProvider
{
    /** @var list<string> */
    private const array LISTENERS = [
        'Shop\\Listeners\\RelinkOrders',
        'Shop\\Listeners\\MergeLoyaltyPoints',
        'Shop\\Listeners\\NotifyAccountOwner',
    ];

    #[Sinful(DeadEventWiring::class)]
    public function boot(): void
    {
        foreach (self::LISTENERS as $listener) {
            Event::listen(CustomerMerged::class, $listener);
        }
    }
}
