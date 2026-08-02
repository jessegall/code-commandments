<?php

namespace Shop\Fulfillment;

use Illuminate\Database\Eloquent\Model;
use Shop\Models\Picker;

/**
 * One trip round the aisles. The far end of {@see Picker::rounds} — an association is symmetric by
 * nature, so this half names the other exactly as the other half names this one.
 */
class PickRound extends Model
{
    /**
     * The inverse of the same single relation. Neither end is the accidental one, so neither is an
     * arrow a reader could cut to make the pair point one way.
     */
    public function picker()
    {
        return $this->belongsTo(Picker::class);
    }
}
