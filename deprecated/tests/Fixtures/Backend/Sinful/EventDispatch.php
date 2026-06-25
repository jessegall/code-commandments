<?php

namespace App\Services;

use App\Events\UserCreatedEvent;
use App\Events\OrderShippedEvent;

class UserService
{
    public function createUser(array $data)
    {
        // Create user...

        // Sin: Using static ::dispatch() on event class
        UserCreatedEvent::dispatch($data);
    }

    public function shipOrder($orderId)
    {
        // Ship order...

        // Sin: Also using static dispatch
        OrderShippedEvent::dispatch($orderId);
    }
}
