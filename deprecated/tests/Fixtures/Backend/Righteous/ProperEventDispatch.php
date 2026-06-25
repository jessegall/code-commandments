<?php

namespace App\Services;

use App\Events\UserCreatedEvent;
use App\Events\OrderShippedEvent;

class UserService
{
    public function createUser(array $data)
    {
        // Create user...

        // Righteous: Using event() helper
        event(new UserCreatedEvent($data));
    }

    public function shipOrder($orderId)
    {
        // Ship order...

        // Righteous: Also using event() helper
        event(new OrderShippedEvent($orderId));
    }
}
