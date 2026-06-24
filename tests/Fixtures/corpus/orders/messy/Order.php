<?php

namespace App\Orders;

class Order
{
    public $id;
    public $customerId;
    public $status;
    public $lineItems;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->customerId = $data['customer_id'] ?? null;
        // status is just a string, one of 'pending','paid','shipped','completed','cancelled'
        $this->status = $data['status'] ?? 'pending';
        $this->lineItems = $data['line_items'] ?? [];
    }

    public function isPending()
    {
        return $this->status == 'pending';
    }

    public function canTransitionTo($next)
    {
        $status = $this->status;

        if ($status == 'pending') {
            return in_array($next, ['paid', 'cancelled']);
        } elseif ($status == 'paid') {
            return in_array($next, ['shipped', 'cancelled']);
        } elseif ($status == 'shipped') {
            return $next == 'completed';
        } else {
            return false;
        }
    }
}
