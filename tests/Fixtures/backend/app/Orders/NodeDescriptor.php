<?php

namespace Shop\Orders;

/**
 * Knows its own branch handles — so "does it have this branch?" is a question it
 * could answer itself, instead of exporting the list for a caller to query.
 */
final class NodeDescriptor
{
    /**
     * @return list<string>
     */
    public function handleNames(): array
    {
        return ['then', 'else', 'default'];
    }
}
