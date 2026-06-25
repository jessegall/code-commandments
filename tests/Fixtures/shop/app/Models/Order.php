<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shop\Enums\OrderStatus;
use Shop\ValueObjects\Money;

class Order extends Model
{
    protected $fillable = ['customer_id', 'status', 'total_cents'];

    protected $casts = ['status' => OrderStatus::class];

    public function scopeForCustomer(Builder $query, int $customerId): void
    {
        $query->where('customer_id', $customerId);
    }

    public function scopePaid(Builder $query): void
    {
        $query->where('status', OrderStatus::Paid);
    }

    public function total(): Money
    {
        return Money::ofCents($this->total_cents);
    }

    public function markPaid(): void
    {
        $this->status = OrderStatus::Paid;
        $this->paid_at = $this->freshTimestamp();
        $this->save();
    }
}
