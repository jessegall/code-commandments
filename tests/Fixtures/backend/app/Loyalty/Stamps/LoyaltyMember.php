<?php

namespace Shop\Loyalty\Stamps;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shop\Models\Order;

class LoyaltyMember extends Model
{
    public function stampedOrders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
