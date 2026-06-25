<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Shop\ValueObjects\Money;

class Product extends Model
{
    protected $fillable = ['name', 'price_cents'];

    public function price(): Money
    {
        return Money::ofCents($this->price_cents);
    }
}
