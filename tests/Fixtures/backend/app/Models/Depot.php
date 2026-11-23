<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A depot parcels wait in until a courier collects them.
 */
class Depot extends Model
{
    public function lockers(): HasMany
    {
        return $this->hasMany(DepotLocker::class);
    }
}
