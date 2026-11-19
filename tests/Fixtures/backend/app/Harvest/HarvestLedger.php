<?php

namespace Shop\Harvest;

use Illuminate\Database\ConnectionInterface;

final class HarvestLedger
{
    public function __construct(private ConnectionInterface $database) {}

    public function kilosPicked(string $crop): int
    {
        return (int) $this->database->table('harvests')->where('crop', $crop)->sum('kilos');
    }
}
