<?php

namespace Shop\Harvest;

use Illuminate\Database\ConnectionInterface;

final class CropCalendar
{
    public function __construct(private ConnectionInterface $database) {}

    /**
     * @return list<string>
     */
    public function bedsRipeOn(int $day): array
    {
        return $this->database->table('crops')->where('ripe_on', '<=', $day)->pluck('bed')->all();
    }
}
