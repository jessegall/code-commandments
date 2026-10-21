<?php

namespace Shop\Dispatch;

/**
 * Where a depot stands: the street, the town and the postcode it is reached by.
 */
final readonly class DepotAddress
{
    public function __construct(public string $street, private string $town, public string $postcode) {}

    public function town(): string
    {
        return $this->town;
    }
}
