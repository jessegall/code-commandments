<?php

namespace Shop\Shipping;

final class Itinerary
{
    /** @var list<string> */
    public array $legModes = [];

    public string $reference = '';
}
