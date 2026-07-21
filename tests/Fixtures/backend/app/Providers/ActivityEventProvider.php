<?php

namespace Shop\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadEventWiring;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Events\AnnouncesShopActivity;

/**
 * Read as dead wiring — nothing in this tree ever raises an AnnouncesShopActivity — yet the listener is
 * the extension point itself: hosts implement the interface on their own events, and the binding catches
 * every one of them. An absence of implementors HERE proves nothing about reachability.
 */
class ActivityEventProvider extends ServiceProvider
{
    #[Righteous(DeadEventWiring::class)]
    public function boot(): void
    {
        Event::listen(AnnouncesShopActivity::class, 'Shop\\Listeners\\RecordActivity');
    }
}
