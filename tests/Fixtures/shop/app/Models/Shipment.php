<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Shop\Enums\ShippingMethod;

class Shipment extends Model
{
    protected $fillable = ['order_id', 'method', 'tracking_code'];

    protected $casts = ['method' => ShippingMethod::class];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
