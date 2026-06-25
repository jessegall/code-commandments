<?php

namespace App\FeatureEnvy\UserFullname;

/**
 * Assembles notification copy by asking each user for its own greeting.
 */
final class NotificationBuilder
{
    public function welcome(User $user): string
    {
        return 'Welcome aboard, '.$user->displayName().'!';
    }

    public function formalGreeting(User $user): string
    {
        return 'Dear '.$user->salutation().',';
    }

    public function emailSubject(User $user): string
    {
        return 'A message for '.$user->displayName();
    }

    public function signatureLine(User $user, NotificationChannel $channel): string
    {
        return $user->credential().' ('.$channel->value.')';
    }
}
