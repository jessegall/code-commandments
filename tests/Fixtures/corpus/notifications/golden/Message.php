<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * The typed, ready-to-send payload of a single notification.
 */
final readonly class Message
{
    public function __construct(
        public string $recipient,
        public string $subject,
        public string $body,
        public NotificationType $type,
    ) {}
}
