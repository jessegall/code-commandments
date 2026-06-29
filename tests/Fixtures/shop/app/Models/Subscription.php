<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Shop\Enums\OrderStatus;

/**
 * Righteous twin for ArrayReturnBagDetector: Eloquent's `casts()` hook returns a
 * config map the framework reads as a raw array — it can't be a value object, so it
 * must NOT be flagged.
 */
class Subscription extends Model
{
    protected $fillable = ['customer_id', 'status', 'renews_at'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'renews_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }
}
