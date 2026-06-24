<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Delivers a notification as a device push notification.
 */
final class PushChannel implements Channel
{
    public function send(Message $message): void
    {
        // Hand the message to the push provider.
    }
}
