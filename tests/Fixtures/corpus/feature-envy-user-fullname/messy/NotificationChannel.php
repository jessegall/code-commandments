<?php

namespace App\FeatureEnvy\UserFullname;

/**
 * The medium a notification is delivered over.
 */
enum NotificationChannel: string
{
    /** Delivered to the user's inbox. */
    case Email = 'email';
    /** Delivered as a text message. */
    case Sms = 'sms';
    /** Delivered as a device push notification. */
    case Push = 'push';
}
