<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Marker interface for a transport that can deliver a notification message.
 */
interface Channel
{
    public function send(Message $message): void;
}
