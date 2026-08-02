<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Shop\Fulfillment\PickRound;

/**
 * The warehouse side of a user. It names PickRound and PickRound names it back, so the two
 * namespaces really do point at each other — and that is the ASSOCIATION working, not a cycle.
 */
class Picker extends Model
{
    /**
     * Eloquent requires BOTH ends of a relation to name each other's class, so this reference
     * carries no direction: there is nothing to invert, and cutting it deletes the relation rather
     * than redirecting it.
     */
    public function rounds()
    {
        return $this->hasMany(PickRound::class);
    }
}
