<?php

namespace Shop\Shipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A consignment handed to a courier, holding the parcels it carries.
 */
class CourierConsignment extends Model
{
    protected $fillable = ['courier', 'handed_over_at'];

    /**
     * @return HasMany<ConsignmentParcel, $this>
     */
    public function parcels(): HasMany
    {
        return $this->hasMany(ConsignmentParcel::class);
    }
}
