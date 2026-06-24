<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * The kind of notification being dispatched, which dictates its transport.
 */
enum NotificationType: string
{
    /** A transactional email, e.g. a receipt or password reset. */
    case Email = 'email';

    /** A short text message for time-critical, low-bandwidth alerts. */
    case Sms = 'sms';

    /** A device push notification for app re-engagement. */
    case Push = 'push';

    public function channelKey(): string
    {
        return match ($this) {
            NotificationType::Email => 'email',
            NotificationType::Sms => 'sms',
            NotificationType::Push => 'push',
        };
    }

    public function isUrgent(): bool
    {
        return match ($this) {
            NotificationType::Sms, NotificationType::Push => true,
            NotificationType::Email => false,
        };
    }
}
