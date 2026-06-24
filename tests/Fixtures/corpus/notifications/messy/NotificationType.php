<?php

namespace App\Notifications;

// closed set as class consts + magic strings instead of an enum
class NotificationType
{
    const EMAIL = 'email';
    const SMS = 'sms';
    const PUSH = 'push';

    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING = 'pending';

    public static function channelKey($type)
    {
        // if/elseif ladder + string comparisons
        if ($type == 'email') {
            return 'email';
        } elseif ($type == 'sms') {
            return 'sms';
        } elseif ($type == 'push') {
            return 'push';
        }

        return 'email';
    }

    public static function isUrgent($type)
    {
        if ($type == 'sms' || $type == 'push') {
            return true;
        }

        return false;
    }
}
