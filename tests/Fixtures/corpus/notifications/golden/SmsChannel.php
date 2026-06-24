<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Delivers a notification as a short text message.
 */
final class SmsChannel implements Channel
{
    public function send(Message $message): void
    {
        // Hand the message to the SMS gateway.
    }
}
