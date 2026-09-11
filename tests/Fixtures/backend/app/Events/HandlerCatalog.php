<?php

namespace Shop\Events;

/**
 * The event handlers the shop discovered — the classes that know how to register themselves.
 */
final class HandlerCatalog
{
    /**
     * @return list<class-string<RegistersHandlers>>
     */
    public function discovered(): array
    {
        return [];
    }
}
