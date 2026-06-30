<?php

namespace Shop\Shipping;

final class ZoneTable
{
    public function lookup(string $zoneId): Zone
    {
        return new Zone();
    }
}
