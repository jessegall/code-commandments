<?php

namespace Shop\Events;

use Illuminate\Contracts\Events\Dispatcher;
use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Wires every discovered handler into the dispatcher at boot.
 */
final class HandlerWiring
{
    /**
     * A loop over the catalog's collection whose per-element work joins each element with the OTHER
     * collaborator this method was handed — orchestration between two objects, not a question the
     * catalog should answer itself. Moving it onto the catalog would make a discoverer know about
     * event dispatch.
     */
    #[Righteous(FeatureEnvy::class)]
    public function boot(Dispatcher $events, HandlerCatalog $catalog): void
    {
        foreach ($catalog->discovered() as $handler) {
            $handler::register($events);
        }
    }
}
