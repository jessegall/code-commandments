<?php

namespace App\Notifications;

/**
 * Delivers a notification as a transactional email.
 */
final class EmailChannel implements Channel
{
    public function send(Message $message): void
    {
        // Hand the message to the mail transport.
    }
}
